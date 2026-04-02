<?php

namespace App\Services;

use Config\Petsfolio;

class LlmService
{
    public function __construct(private readonly Petsfolio $config = new Petsfolio()) {}

    public function answerFromContext(string $question, array $history, array $contextBlocks, string $locale = 'en'): ?string
    {
        if (!$this->config->llmEnabled) {
            return null;
        }

        $cacheKey = 'petsfolio-llm-' . md5($locale . '|' . $question . '|' . json_encode($contextBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $cached   = $this->cacheGet($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $client = \Config\Services::curlrequest([
            'timeout' => $this->config->llmTimeout,
        ]);

        $payload = [
            'model'   => $this->config->llmModel,
            'prompt'  => $this->buildPrompt($question, $history, $contextBlocks, $locale),
            'stream'  => false,
            'options' => [
                'temperature' => 0.05,
                'num_predict' => 260,
            ],
        ];

        try {
            $response = $client->post($this->config->llmBaseUrl . '/api/generate', [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
            $data     = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $text     = trim((string) ($data['response'] ?? ''));

            if ($text === '') {
                return null;
            }

            $this->cacheSave($cacheKey, $text);

            return $text;
        } catch (\Throwable $exception) {
            log_message('error', 'Petsfolio LLM request failed: ' . $exception->getMessage());
            return null;
        }
    }

    public function refusal(string $locale = 'en'): string
    {
        return match ($locale) {
            'hi' => 'मैं केवल पालतू बीमा से जुड़े सवालों में मदद कर सकता हूँ। कृपया पॉलिसी, कवर, कीमत या क्लेम के बारे में पूछें।',
            'te' => 'నేను పెట్ ఇన్షూరెన్స్‌కు సంబంధించిన ప్రశ్నలకే సహాయం చేయగలను. దయచేసి పాలసీ, కవర్, ధర లేదా క్లెయిమ్ గురించి అడగండి.',
            'kn' => 'ನಾನು ಪೆಟ್ ಇನ್ಶೂರೆನ್ಸ್‌ಗೆ ಸಂಬಂಧಿಸಿದ ಪ್ರಶ್ನೆಗಳಲ್ಲೇ ಸಹಾಯ ಮಾಡಬಲ್ಲೆ. ದಯವಿಟ್ಟು ಪಾಲಿಸಿ, ಕವರ್, ಬೆಲೆ ಅಥವಾ ಕ್ಲೈಮ್ ಬಗ್ಗೆ ಕೇಳಿ.',
            'ta' => 'நான் செல்லப்பிராணி காப்பீட்டுக்கான கேள்விகளிலேயே உதவ முடியும். பாலிசி, கவர், விலை அல்லது க்ளெயிம் பற்றி கேளுங்கள்.',
            default => 'I can only help with pet insurance questions. Please ask about policies, coverage, pricing, or claims.',
        };
    }

    public function askStream(array $history, string $locale = 'en', callable $onChunk = null, callable $onDone = null): void
    {
        if (!$this->config->llmEnabled) {
            if ($onDone) $onDone('LLM is disabled in config.');
            return;
        }

        $question = '';
        foreach (array_reverse($history) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $question = $msg['content'] ?? '';
                break;
            }
        }

        $response = $this->streamPrompt(
            $this->buildPrompt($question, array_slice($history, 0, -1), [], $locale),
            500,
            $onChunk,
            0.1
        );

        if ($onDone) {
            $onDone($response === null ? 'Unable to stream a response from Petsfolio.' : null);
        }
    }

    public function streamAnswerFromContext(
        string $question,
        array $history,
        array $contextBlocks,
        string $locale = 'en',
        callable $onChunk = null
    ): ?string {
        if (!$this->config->llmEnabled) {
            return null;
        }

        $cacheKey = 'petsfolio-llm-' . md5($locale . '|' . $question . '|' . json_encode($contextBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $cached   = $this->cacheGet($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $this->emitTextChunks($cached, $onChunk);
            return $cached;
        }

        $response = $this->streamPrompt(
            $this->buildPrompt($question, $history, $contextBlocks, $locale),
            260,
            $onChunk,
            0.05
        );

        if ($response !== null && trim($response) !== '') {
            $this->cacheSave($cacheKey, $response);
        }

        return $response;
    }

    private function buildPrompt(string $question, array $history, array $contextBlocks, string $locale): string
    {
        $language = match ($locale) {
            'hi' => 'Hindi',
            'te' => 'Telugu',
            'kn' => 'Kannada',
            'ta' => 'Tamil',
            default => 'English',
        };

        $historyText = '';
        foreach ($history as $item) {
            $role    = strtoupper((string) ($item['role'] ?? 'user'));
            $content = trim((string) ($item['content'] ?? ''));
            if ($content !== '') {
                $historyText .= $role . ': ' . $content . "\n";
            }
        }

        $contextText = '';
        foreach ($contextBlocks as $index => $block) {
            $contextText .= '[' . ($index + 1) . '] ' . ($block['title'] ?? 'Insurance Source') . ': ' . trim((string) ($block['content'] ?? '')) . "\n";
        }

        return <<<PROMPT
                SYSTEM:
                You are the Petsfolio Insurance Assistant.

                Strict rules:
                1. You only answer pet insurance questions.
                2. If the user asks anything outside pet insurance, including your name, identity, jokes, weather, news, coding, or general chat, politely refuse and redirect to pet insurance.
                3. Use only the provided CONTEXT and recent chat. Do not use outside knowledge.
                4. If the answer is not clearly supported by the CONTEXT, say the information is not available in the current insurance data.
                5. Do not invent policy wording, prices, claim steps, waiting periods, exclusions, or recommendations.
                6. Ignore irrelevant context. If retrieved text does not answer the question, say it is not available.
                7. If the user only greets you, greet briefly and ask what pet insurance help they need.
                8. Keep the answer simple and practical.
                9. Respond in {$language}.

                RECENT CHAT:
                {$historyText}

                CONTEXT:
                {$contextText}

                USER QUESTION:
                {$question}

                ANSWER:
                PROMPT;
    }

    private function streamPrompt(string $prompt, int $numPredict, ?callable $onChunk = null, float $temperature = 0.05): ?string
    {
        $payload = [
            'model'   => $this->config->llmModel,
            'prompt'  => $prompt,
            'stream'  => true,
            'options' => [
                'temperature' => $temperature,
                'num_predict' => $numPredict,
            ],
        ];

        $url = $this->config->llmBaseUrl . '/api/generate';
        $ch  = curl_init($url);

        $fullResponse = '';
        $lineBuffer = '';
        $emitBuffer = '';

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($onChunk, &$lineBuffer, &$emitBuffer, &$fullResponse) {
            $lineBuffer .= $data;

            while (($pos = strpos($lineBuffer, "\n")) !== false) {
                $line = trim(substr($lineBuffer, 0, $pos));
                $lineBuffer = substr($lineBuffer, $pos + 1);

                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);
                if (!isset($decoded['response'])) {
                    continue;
                }

                $chunk = (string) $decoded['response'];
                if ($chunk === '') {
                    continue;
                }

                $fullResponse .= $chunk;
                $emitBuffer .= $chunk;

                if (preg_match('/^(.*\s)(\S*)$/us', $emitBuffer, $matches) === 1) {
                    $this->emitTextChunks($matches[1], $onChunk);
                    $emitBuffer = $matches[2];
                }
            }

            return strlen($data);
        });

        $ok = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($emitBuffer !== '') {
            $this->emitTextChunks($emitBuffer, $onChunk);
        }

        if ($ok === false && trim($fullResponse) === '') {
            log_message('error', 'Petsfolio streaming request failed: ' . ($err ?: 'Unknown streaming error'));
            return null;
        }

        $text = trim($fullResponse);

        return $text === '' ? null : $text;
    }

    private function emitTextChunks(string $text, ?callable $onChunk): void
    {
        if ($onChunk === null || $text === '') {
            return;
        }

        $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || $parts === []) {
            $onChunk($text);
            return;
        }

        $buffer = '';
        $tokenCount = 0;

        foreach ($parts as $part) {
            $buffer .= $part;

            if (!preg_match('/^\s+$/u', $part)) {
                $tokenCount++;
            }

            if ($tokenCount >= 3 || mb_strlen($buffer) >= 24 || str_contains($part, "\n")) {
                $onChunk($buffer);
                $buffer = '';
                $tokenCount = 0;
            }
        }

        if ($buffer !== '') {
            $onChunk($buffer);
        }
    }

    private function cacheGet(string $key): mixed
    {
        try {
            return cache()->get($key);
        } catch (\Throwable $exception) {
            log_message('warning', 'Petsfolio LLM cache read skipped: ' . $exception->getMessage());
            return null;
        }
    }

    private function cacheSave(string $key, string $value): void
    {
        try {
            cache()->save($key, $value, $this->config->cacheTtl);
        } catch (\Throwable $exception) {
            log_message('warning', 'Petsfolio LLM cache write skipped: ' . $exception->getMessage());
        }
    }
}
