<?php

namespace App\Services;

class LlmService
{
    public function ask($history, $locale = 'en')
    {
        // If history is a string, wrap it as a single user message
        if (is_string($history)) {
            $history = [['role' => 'user', 'content' => $history]];
        }
        $client = \Config\Services::curlrequest();
        $payload = [
            'model' => 'llama3',
            'prompt' => $this->buildPrompt($history, $locale),
            'stream' => false
        ];

        $res = $client->post('http://localhost:11434/api/generate', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($payload)
        ]);

        $data = json_decode($res->getBody(), true);

        // Normalize to a text string
        $text = '';
        if (is_string($data)) {
            $text = $data;
        } elseif (is_array($data)) {
            $text = $data['response'] ?? $data['text'] ?? ($data['message']['content'] ?? json_encode($data));
        }

        $text = trim($text);

        // If model returned an English refusal or included a 'Translation:' line,
        // override with our canonical refusal in the requested locale.
        $englishTrigger = 'I can only assist';
        if (stripos($text, $englishTrigger) !== false || stripos($text, 'Translation:') !== false) {
            return $this->refusalForLocale($locale);
        }

        // Normalize response: strip Q/A prefixes (e.g. "Jawab:") and extract
        // the actual answer if the model returned question+answer pairs.
        $text = $this->normalizeResponse($text, $locale);

        return $text !== '' ? $text : '{}';
    }

    /**
     * Stream tokens from the LLM endpoint and invoke callbacks for each chunk.
     *
     * Usage:
     *   $service->askStream($prompt, function($chunk) { /* handle partial chunk * / }, function($errOrNull) { done});
     *
     * The LLM endpoint is expected to support streaming when passed `stream: true` and
     * to emit either JSON-per-line or text chunks. This method attempts to decode JSON
     * chunks and fallbacks to raw text.
     *
     * @param string $prompt
     * @param callable $onChunk function(string $text)
     * @param callable|null $onDone function(?string $error)
     * @return void
     */
    public function askStream($history, string $locale, callable $onChunk, callable $onDone = null)
    {
        // If history is a string, wrap it
        if (is_string($history)) {
            $history = [['role' => 'user', 'content' => $history]];
        }

        $endpoint = 'http://localhost:11434/api/generate';

        $payload = json_encode([
            'model' => 'llama3',
            'prompt' => $this->buildPrompt($history, $locale),
            'stream' => true
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        $sentRefusal = false;
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use ($onChunk, $locale, &$sentRefusal) {
            // Ollama can send multiple JSON objects in one chunk, usually separated by newlines.
            // Split by newline and process each line.
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $trim = trim($line);
                if ($trim === '') continue;

                $decoded = json_decode($trim, true);
                if ($sentRefusal) {
                    continue;
                }

                if (is_array($decoded)) {
                    $text = $decoded['response'] ?? ($decoded['message']['content'] ?? $decoded['token'] ?? '');
                    if ($text !== '') {
                        // Detect English refusal/translation artifacts
                        $englishTrigger = 'I can only assist';
                        if (stripos($text, $englishTrigger) !== false || stripos($text, 'Translation:') !== false) {
                            try { call_user_func($onChunk, $this->refusalForLocale($locale)); } catch (\Exception $e) {}
                            $sentRefusal = true;
                        } else {
                            // Strip common Q/A prefixes (e.g. "Jawab:") from streaming chunks
                            $clean = $this->stripAnswerMarkers($text);
                            try { call_user_func($onChunk, $clean); } catch (\Exception $e) {}
                        }
                    }
                } else {
                    // Fallback for raw text if not JSON
                    $englishTrigger = 'I can only assist';
                    if (stripos($trim, $englishTrigger) !== false || stripos($trim, 'Translation:') !== false) {
                        try { call_user_func($onChunk, $this->refusalForLocale($locale)); } catch (\Exception $e) {}
                        $sentRefusal = true;
                    } else {
                        $clean = $this->stripAnswerMarkers($trim);
                        try { call_user_func($onChunk, $clean); } catch (\Exception $e) {}
                    }
                }
            }
            return strlen($data);
        });

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($onDone) {
            try { call_user_func($onDone, $err ?: null); } catch (\Exception $_) {}
        }
    }

    private function formatHistory($history, $locale = 'en')
    {
        // History support removed: always return only the system message.
        $langMap = ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'zh' => 'Chinese', 'ja' => 'Japanese', 'ko' => 'Korean', 'ru' => 'Russian', 'pt' => 'Portuguese', 'it' => 'Italian', 'nl' => 'Dutch', 'pl' => 'Polish', 'tr' => 'Turkish', 'ar' => 'Arabic', 'hi' => 'Hindi', 'vi' => 'Vietnamese', 'id' => 'Indonesian', 'ms' => 'Malay', 'th' => 'Thai', 'el' => 'Greek', 'bg' => 'Bulgarian', 'ca' => 'Catalan', 'hr' => 'Croatian', 'cs' => 'Czech', 'da' => 'Danish', 'fa' => 'Persian', 'fi' => 'Finnish', 'he' => 'Hebrew', 'hu' => 'Hungarian', 'la' => 'Latin', 'ro' => 'Romanian', 'sk' => 'Slovak', 'sl' => 'Slovenian', 'sv' => 'Swedish', 'sw' => 'Swahili', 'ta' => 'Tamil', 'te' => 'Telugu', 'ur' => 'Urdu', 'uk' => 'Ukrainian', 'et' => 'Estonian', 'lt' => 'Lithuanian', 'lv' => 'Latvian', 'mk' => 'Macedonian', 'ml' => 'Malayalam', 'mr' => 'Marathi', 'ne' => 'Nepali', 'or' => 'Odia', 'pa' => 'Punjabi', 'si' => 'Sinhala', 'su' => 'Sundanese', 'ti' => 'Tigrinya', 'tk' => 'Turkmen', 'tl' => 'Tagalog', 'ur' => 'Urdu', 'yi' => 'Yiddish', 'zu' => 'Zulu'];
        $langName = $langMap[$locale] ?? 'English';

        $refusalMap = [
            'en' => 'I can only assist with pet insurance questions. Please ask about pet insurance policies, coverage, claims, or related topics.',
            'te' => 'నేను పెట్ ఇన్సూరెన్స్ సంబంధించిన ప్రశ్నలకు మాత్రమే సహాయం చేయగలను. దయచేసి పెట్ ఇన్సూరెన్స్ పాలసీల గురించి, కవర్, క్లెయిమ్‌లు లేదా సంబంధిత విషయాల గురించి అడగండి.',
            'es' => 'Solo puedo ayudar con preguntas sobre seguros para mascotas. Por favor, pregunte sobre pólizas de seguros para mascotas, coberturas, reclamaciones u otros temas relacionados.',
            'de' => 'Ich kann nur bei Fragen zur Tierkrankenversicherung helfen. Bitte fragen Sie nach Versicherungspolicen für Haustiere, Deckung, Ansprüchen oder verwandten Themen.',
            'fr' => 'Je ne peux aider que pour des questions d\'assurance pour animaux de compagnie. Veuillez poser des questions sur les polices d\'assurance pour animaux, les couvertures, les demandes d\'indemnisation ou des sujets connexes.',
            'zh' => '我只能协助处理宠物保险相关问题。请询问有关宠物保险的保单、保障、索赔或相关话题。',
            'ja' => 'ペット保険に関する質問にのみ対応できます。ペット保険の保険契約、補償、請求、または関連するトピックについて質問してください。',
            'hi' => 'मैं केवल पालतू बीमा से संबंधित प्रश्नों में सहायता कर सकता/सकती हूँ। कृपया पालतू बीमा नीतियों, कवरेज, दावों या संबंधित विषयों के बारे में पूछें।',
            'pt' => 'Só posso ajudar com perguntas sobre seguro para animais de estimação. Por favor, pergunte sobre apólices de seguro para animais, cobertura, sinistros ou temas relacionados.'
        ];

        $refusal = $refusalMap[$locale] ?? $refusalMap['en'];

        $systemMsg = [
            'role' => 'system',
            'content' => "You are Petsfolio Insurance Assistant. Your ONLY purpose is to answer questions about PET INSURANCE.\\n\\n" .
                         "CRITICAL RULES:\\n" .
                         "1. MUST respond ONLY in " . $langName . ". NEVER use any other language.\\n" .
                         "2. ONLY answer questions about pet insurance, coverage, claims, policies, or related topics.\\n" .
                         "3. If the user asks about ANYTHING other than pet insurance (movies, TV shows, politics, recipes, etc.), IMMEDIATELY respond with ONLY the following exact sentence (and nothing else):\\n" .
                         "   \"" . $refusal . "\"\\n" .
                         "4. Do NOT provide off-topic information under any circumstance.\\n" .
                         "5. Be professional and helpful when answering pet insurance questions.\\n" .
                         "6. NEVER ask the user any clarifying questions. Always provide a direct, concise answer using the information available.\\n" .
                         "   If you truly lack required details to form an answer, respond with a short statement in the user's language that you don't have enough information to answer and provide a best-effort general response. DO NOT phrase this as a question or prompt the user for more input.\\n\\n" .
                         "Remember: Your role is STRICTLY limited to pet insurance support. Reject any non-insurance questions firmly and only in the user's language."
        ];

        return [$systemMsg];
    }

    private function buildPrompt($history, $locale = 'en')
    {
        // Determine user content string (history support removed — only use current prompt)
        $userContent = '';
        if (is_string($history)) {
            $userContent = $history;
        } elseif (is_array($history)) {
            // If an array was passed, extract the last user role content if present
            for ($i = count($history) - 1; $i >= 0; $i--) {
                if (isset($history[$i]['role']) && $history[$i]['role'] === 'user' && !empty($history[$i]['content'])) {
                    $userContent = $history[$i]['content'];
                    break;
                }
            }
        }

        $system = $this->formatHistory([], $locale)[0]['content'] ?? '';

        $prompt = "SYSTEM:\n" . $system . "\n\n---\n\nUSER:\n" . $userContent;
        return $prompt;
    }

    private function refusalForLocale($locale = 'en')
    {
        $refusalMap = [
            'en' => 'I can only assist with pet insurance questions. Please ask about pet insurance policies, coverage, claims, or related topics.',
            'te' => 'నేను పెట్ ఇన్సూరెన్స్ సంబంధించిన ప్రశ్నలకు మాత్రమే సహాయం చేయగలను. దయచేసి పెట్ ఇన్సూరెన్స్ పాలసీల గురించి, కవర్, క్లెయిమ్‌లు లేదా సంబంధిత విషయాల గురించి అడగండి.',
            'es' => 'Solo puedo ayudar con preguntas sobre seguros para mascotas. Por favor, pregunte sobre pólizas de seguros para mascotas, coberturas, reclamaciones u otros temas relacionados.',
            'de' => 'Ich kann nur bei Fragen zur Tierkrankenversicherung helfen. Bitte fragen Sie nach Versicherungspolicen für Haustiere, Deckung, Ansprüchen oder verwandten Themen.',
            'fr' => 'Je ne peux aider que pour des questions d\'assurance pour animaux de compagnie. Veuillez poser des questions sur les polices d\'assurance pour animaux, les couvertures, les demandes d\'indemnisation ou des sujets connexes.',
            'zh' => '我只能协助处理宠物保险相关问题。请询问有关宠物保险的保单、保障、索赔或相关话题。',
            'ja' => 'ペット保険に関する質問にのみ対応できます。ペット保険の保険契約、補償、請求、または関連するトピックについて質問してください。',
            'hi' => 'मैं केवल पालतू बीमा से संबंधित प्रश्नों में सहायता कर सकता/सकती हूँ। कृपया पालतू बीमा नीतियों, कवरेज, दावों या संबंधित विषयों के बारे में पूछें।',
            'pt' => 'Só posso ajudar com perguntas sobre seguro para animais de estimação. Por favor, pergunte sobre apólices de seguro para animais, cobertura, sinistros ou temas relacionados.'
        ];

        return $refusalMap[$locale] ?? $refusalMap['en'];
    }

    /**
     * Strip common Q/A prefixes (streaming-safe) like "Jawab:", "Answer:", "उत्तर:" etc.
     */
    private function stripAnswerMarkers(string $text): string
    {
        if (!is_string($text) || trim($text) === '') return '';
        return preg_replace('/^\s*(?:Jawab[:\-]?|Jawab|Answer[:\-]?|Answer|उत्तर[:\-]?|उत्तर|Ans[:\-]?|A[:\-]?)\s*/iu', '', $text);
    }

    /**
     * Normalize a full response: remove translation lines, strip Q/A wrappers
     * and attempt to extract the final answer when the model returns Q/A pairs.
     */
    private function normalizeResponse(string $text, string $locale = 'en'): string
    {
        $text = trim($text);
        if ($text === '') return '';

        // Remove explicit translation markers
        $text = preg_replace('/\bTranslation\b\s*[:\-]\s*/i', '', $text);
        $text = preg_replace('/\bअनुवाद\b\s*[:\-]\s*/iu', '', $text);

        // Quick direct checks for common localized "answer" markers (fallback)
        $directMarkers = ['jawab:', 'jawab', 'answer:', 'answer', 'उत्तर:', 'उत्तर', 'ans:', 'a:'];
        foreach ($directMarkers as $dm) {
            $pos = mb_stripos($text, $dm);
            if ($pos !== false) {
                $start = $pos + mb_strlen($dm);
                $candidate = trim(mb_substr($text, $start));
                if ($candidate !== '') return $candidate;
            }
        }

        // If there's an explicit 'Answer:' / 'Jawab:' marker, take everything after
        if (preg_match_all('/(?:Answer|Jawab|उत्तर|Ans|A)\s*(?:[:\-])?/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
            $last = end($m[0]);
            $start = $last[1] + strlen($last[0]);
            $candidate = trim(mb_substr($text, $start));
            if ($candidate !== '') return $candidate;
        }

        // Otherwise, split into lines and pick the last contiguous block that looks like an answer
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), function($l) { return $l !== ''; }));
        if (empty($lines)) return '';

        $answerLines = [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            // Skip translation lines
            if (preg_match('/Translation|अनुवाद|翻译/i', $line)) continue;
            // Stop if we hit a question marker or a labelled question
            if (preg_match('/^\s*(?:Q:|Question|Q\.|प्रश्न|Pregunta|Frage|Domanda|Soru)\b/i', $line)) {
                break;
            }
            if (substr($line, -1) === '?') {
                // likely a question; skip it
                continue;
            }
            array_unshift($answerLines, $line);
            // gather preceding lines that are likely part of the answer
            $j = $i - 1;
            while ($j >= 0) {
                $pl = $lines[$j];
                if (preg_match('/^\s*(?:Q:|Question|प्रश्न|Pregunta|Frage|Domanda)\b/i', $pl)) break;
                if (substr($pl, -1) === '?') break;
                array_unshift($answerLines, $pl);
                $j--;
                $i = $j + 1;
            }
            break;
        }

        if (!empty($answerLines)) {
            return trim(implode("\n", $answerLines));
        }

        // Fallback to last non-empty line
        return trim(end($lines));
    }

    public function refusal($locale = 'en')
    {
        return $this->refusalForLocale($locale);
    }
}