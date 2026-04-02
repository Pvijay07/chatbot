<?php

namespace App\Services;

use App\Models\ChatModel;
use App\Models\InsurancePlanModel;
use App\Models\MessageModel;
use Config\Petsfolio;

class AssistantService
{
    private ChatModel $chatModel;
    private MessageModel $messageModel;
    private InsurancePlanModel $planModel;
    private UserInsuranceLookupService $userInsuranceLookup;
    private RetrievalService $retrieval;
    private RecommendationService $recommendation;
    private LlmService $llm;

    public function __construct(private readonly Petsfolio $config = new Petsfolio())
    {
        $this->chatModel = new ChatModel();
        $this->messageModel = new MessageModel();
        $this->planModel = new InsurancePlanModel();
        $this->userInsuranceLookup = service('userInsuranceLookup');
        $this->retrieval = service('retrieval');
        $this->recommendation = service('recommendation');
        $this->llm = service('llm');
    }

    public function handleChat(int $userId, string $message, ?int $chatId = null, string $locale = 'en', ?int $lookupUserId = null): array
    {
        $context = $this->prepareConversation($userId, $message, $chatId, $locale);

        $replyData = $this->buildReply(
            $message,
            $lookupUserId,
            $userId,
            $context['history'],
            $context['historyText'],
            $context['petType'],
            $context['locale']
        );

        return $this->storeAssistantReply(
            $context['chat'],
            $context['petType'],
            $message,
            $replyData['reply'],
            $replyData['sources'],
            $context['locale']
        );
    }

    public function handleChatStream(
        int $userId,
        string $message,
        callable $onChunk,
        ?int $chatId = null,
        string $locale = 'en',
        ?int $lookupUserId = null
    ): array {
        $context = $this->prepareConversation($userId, $message, $chatId, $locale);

        $replyData = $this->buildStreamedReply(
            $message,
            $lookupUserId,
            $userId,
            $context['history'],
            $context['historyText'],
            $context['petType'],
            $context['locale'],
            $onChunk
        );

        return $this->storeAssistantReply(
            $context['chat'],
            $context['petType'],
            $message,
            $replyData['reply'],
            $replyData['sources'],
            $context['locale']
        );
    }

    private function prepareConversation(int $userId, string $message, ?int $chatId, string $locale): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('Message is required.');
        }

        $locale = in_array($locale, $this->config->supportedLocales, true) ? $locale : 'en';
        $chat = $this->resolveChat($userId, $chatId, $message);

        $this->messageModel->insert([
            'chat_id'      => $chat['id'],
            'sender'       => 'user',
            'message'      => $message,
            'language'     => $locale,
            'sources_json' => null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $history = $this->messageModel->getRecentContext((int) $chat['id'], $this->config->contextMessageLimit);
        $historyText = implode(' ', array_map(static fn(array $row): string => $row['message'], $history));
        $petType = $this->recommendation->inferPetType($historyText . ' ' . $message);

        return [
            'chat'        => $chat,
            'history'     => $history,
            'historyText' => $historyText,
            'locale'      => $locale,
            'petType'     => $petType,
        ];
    }

    private function buildReply(
        string $message,
        ?int $lookupUserId,
        int $userId,
        array $history,
        string $historyText,
        ?string $petType,
        string $locale
    ): array {
        $lookupReply = $this->userInsuranceLookup->replyForMessage($message, $lookupUserId, $userId, $history, $locale);

        if ($lookupReply !== null) {
            return [
                'reply'   => $lookupReply['reply'],
                'sources' => $lookupReply['sources'],
            ];
        }

        $plans = $this->planModel->activePlans($petType);
        $chunks = $this->retrieval->findRelevantChunks($message . ' ' . $historyText, $locale);
        $contextBlocks = array_merge(
            $this->toPlanContextBlocks($plans, $locale),
            $this->toDocumentContextBlocks($chunks)
        );

        $reply = $this->llm->answerFromContext(
            $message,
            $this->toHistoryRoles($history),
            $contextBlocks,
            $locale
        );

        if ($reply === null || trim($reply) === '') {
            $reply = $this->fallbackReply($message, $historyText, $plans, $chunks, $petType, $locale);
        }

        $sources = $this->mergeSources($reply, $plans, $chunks, $locale);

        return [
            'reply'   => $reply,
            'sources' => $sources,
        ];
    }

    private function buildStreamedReply(
        string $message,
        ?int $lookupUserId,
        int $userId,
        array $history,
        string $historyText,
        ?string $petType,
        string $locale,
        callable $onChunk
    ): array {
        $lookupReply = $this->userInsuranceLookup->replyForMessage($message, $lookupUserId, $userId, $history, $locale);

        if ($lookupReply !== null) {
            $reply = (string) $lookupReply['reply'];
            $this->streamText($reply, $onChunk);

            return [
                'reply'   => $reply,
                'sources' => $lookupReply['sources'],
            ];
        }

        $plans = $this->planModel->activePlans($petType);
        $chunks = $this->retrieval->findRelevantChunks($message . ' ' . $historyText, $locale);
        $contextBlocks = array_merge(
            $this->toPlanContextBlocks($plans, $locale),
            $this->toDocumentContextBlocks($chunks)
        );

        $reply = $this->llm->streamAnswerFromContext(
            $message,
            $this->toHistoryRoles($history),
            $contextBlocks,
            $locale,
            $onChunk
        );

        if ($reply === null || trim($reply) === '') {
            $reply = $this->fallbackReply($message, $historyText, $plans, $chunks, $petType, $locale);
            $this->streamText($reply, $onChunk);
        }

        $sources = $this->mergeSources($reply, $plans, $chunks, $locale);

        return [
            'reply'   => $reply,
            'sources' => $sources,
        ];
    }

    private function fallbackReply(
        string $message,
        string $historyText,
        array $plans,
        array $chunks,
        ?string $petType,
        string $locale
    ): string {
        if ($this->isGreeting($message)) {
            return $this->greeting($locale);
        }

        if (!$this->looksInsuranceLike($message, $historyText)) {
            return $this->llm->refusal($locale);
        }

        if ($chunks !== []) {
            return $this->fallbackFromChunks($chunks, $locale);
        }

        if ($plans !== []) {
            return $this->fallbackFromPlans($plans, $petType, $locale);
        }

        return $this->llm->refusal($locale);
    }

    private function mergeSources(string $reply, array $plans, array $chunks, string $locale): array
    {
        $sources = array_merge(
            $this->planSources($plans, $locale),
            $this->chunkSources($chunks)
        );
        $sources = array_slice($sources, 0, 4);

        if ($this->isStrictRefusal($reply, $locale)) {
            return [];
        }

        return $sources;
    }

    private function storeAssistantReply(
        array $chat,
        ?string $petType,
        string $message,
        string $reply,
        array $sources,
        string $locale
    ): array {
        $this->messageModel->insert([
            'chat_id'      => $chat['id'],
            'sender'       => 'assistant',
            'message'      => $reply,
            'language'     => $locale,
            'sources_json' => $sources === [] ? null : json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $title = $chat['title'] !== 'New chat' ? $chat['title'] : $this->buildChatTitle($message);
        $this->chatModel->update((int) $chat['id'], [
            'title'           => $title,
            'pet_type'        => $petType,
            'last_message_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'chat' => [
                'id'         => (int) $chat['id'],
                'title'      => $title,
                'pet_type'   => $petType,
                'updated_at' => date('c'),
            ],
            'reply'   => $reply,
            'sources' => $sources,
        ];
    }

    private function streamText(string $text, callable $onChunk): void
    {
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

    private function resolveChat(int $userId, ?int $chatId, string $message): array
    {
        if ($chatId !== null) {
            $chat = $this->chatModel->where('id', $chatId)->where('user_id', $userId)->first();
            if ($chat !== null) {
                return $chat;
            }
        }

        $newId = $this->chatModel->insert([
            'user_id'         => $userId,
            'title'           => 'New chat',
            'pet_type'        => $this->recommendation->inferPetType($message),
            'last_message_at' => date('Y-m-d H:i:s'),
        ], true);

        return $this->chatModel->find((int) $newId) ?? [
            'id'    => (int) $newId,
            'title' => 'New chat',
        ];
    }

    private function isGreeting(string $message): bool
    {
        return (bool) preg_match('/^(hi|hello|hey|good morning|good evening|namaste|नमस्ते|హలో|నమస్కారం|ಹಲೋ|ನಮಸ್ಕಾರ|ஹலோ|வணக்கம்)\b/iu', trim($message));
    }

    private function greeting(string $locale): string
    {
        return match ($locale) {
            'hi' => 'मैं Petsfolio पालतू बीमा योजनाओं, कवर, कीमत और क्लेम प्रक्रिया में मदद कर सकता हूँ। आपका पालतू डॉग है या कैट?',
            'te' => 'నేను Petsfolio పెట్ ఇన్షూరెన్స్ ప్లాన్లు, కవర్, ధరలు మరియు క్లెయిమ్ ప్రక్రియలో సహాయం చేయగలను. మీ పెంపుడు జంతువు కుక్కా లేక పిల్లినా?',
            'kn' => 'ನಾನು Petsfolio ಪೆಟ್ ಇನ್ಶೂರೆನ್ಸ್ ಯೋಜನೆಗಳು, ಕವರ್, ಬೆಲೆ ಮತ್ತು ಕ್ಲೈಮ್ ಪ್ರಕ್ರಿಯೆಯಲ್ಲಿ ಸಹಾಯ ಮಾಡಬಹುದು. ನಿಮ್ಮ ಪೆಟ್ ನಾಯಿ ಅಥವಾ ಬೆಕ್ಕು?',
            'ta' => 'நான் Petsfolio செல்லப்பிராணி காப்பீட்டு திட்டங்கள், கவர், விலை மற்றும் க்ளெயிம் செயல்முறையில் உதவலாம். உங்கள் செல்லப்பிராணி நாயா அல்லது பூனையா?',
            default => 'I can help with Petsfolio pet insurance plans, coverage, pricing, and claim guidance. Is your pet a dog or a cat?',
        };
    }

    private function looksInsuranceLike(string $message, string $historyText = ''): bool
    {
        $text = mb_strtolower(trim($message . ' ' . $historyText));

        return (bool) preg_match('/\b(pet|dog|cat|insurance|polic|plan|coverage|claim|premium|price|cost|reimburse|deductible|waiting|illness|accident|vet)|पालतू|डॉग|कैट|बीमा|पॉलिसी|योजना|क्लेम|कवर|कीमत|वेट|పెట్|కుక్క|పిల్లి|బీమా|పాలసీ|ప్లాన్|కవర్|క్లెయిమ్|ధర|వెట్|ಪೆಟ್|ನಾಯಿ|ಬೆಕ್ಕು|ವಿಮೆ|ಪಾಲಿಸಿ|ಯೋಜನೆ|ಕವರ್|ಕ್ಲೈಮ್|ಬೆಲೆ|ವೆಟ್|செல்ல|நாய்|பூனை|காப்பீடு|பாலிசி|திட்டம்|கவர்|க்ளெயிம்|விலை|வெட்/ui', $text);
    }

    private function isStrictRefusal(string $reply, string $locale): bool
    {
        return trim($reply) === trim($this->llm->refusal($locale));
    }

    private function fallbackFromPlans(array $plans, ?string $petType, string $locale): string
    {
        $lines = [];
        foreach (array_slice($plans, 0, 3) as $plan) {
            $lines[] = sprintf(
                '- %s: $%s/month, %s%% reimbursement, $%s annual limit.',
                $this->planName($plan, $locale),
                number_format((float) $plan['price_monthly'], 2),
                (int) $plan['reimbursement_percent'],
                number_format((int) $plan['annual_limit'])
            );
        }

        $petLabel = $petType === null ? 'pet' : $this->petLabel($petType, $locale);

        return match ($locale) {
            'hi' => "Petsfolio {$petLabel} योजनाओं से मुझे यह जानकारी मिली:\n" . implode("\n", $lines) . "\n\nयदि चाहें तो कवरेज, कीमत या क्लेम प्रक्रिया पर और विशेष जानकारी पूछें।",
            'te' => "Petsfolio {$petLabel} ప్లాన్ల నుంచి నాకు దొరికిన సమాచారం ఇది:\n" . implode("\n", $lines) . "\n\nకావాలంటే కవర్, ధర లేదా క్లెయిమ్ ప్రక్రియ గురించి మరింత ప్రత్యేకంగా అడగండి.",
            'kn' => "Petsfolio {$petLabel} ಯೋಜನೆಗಳಿಂದ ನನಗೆ ಸಿಕ್ಕ ಮಾಹಿತಿ ಇದು:\n" . implode("\n", $lines) . "\n\nಬೇಕಾದರೆ ಕವರ್, ಬೆಲೆ ಅಥವಾ ಕ್ಲೈಮ್ ಪ್ರಕ್ರಿಯೆ ಬಗ್ಗೆ ಇನ್ನಷ್ಟು ನಿರ್ದಿಷ್ಟವಾಗಿ ಕೇಳಿ.",
            'ta' => "Petsfolio {$petLabel} திட்டங்களில் இருந்து கிடைத்த தகவல் இது:\n" . implode("\n", $lines) . "\n\nவிருப்பமிருந்தால் கவர், விலை அல்லது க்ளெயிம் செயல்முறை பற்றி இன்னும் குறிப்பாக கேளுங்கள்.",
            default => "Here is the closest matching Petsfolio plan information:\n" . implode("\n", $lines) . "\n\nIf you want, ask more specifically about coverage, pricing, or claims.",
        };
    }

    private function fallbackFromChunks(array $chunks, string $locale): string
    {
        $top = array_slice($chunks, 0, 2);
        $snippets = array_map(static fn(array $chunk): string => '- ' . ($chunk['snippet'] ?? ''), $top);
        $body = implode("\n", $snippets);

        return match ($locale) {
            'hi' => "Petsfolio दस्तावेज़ों के आधार पर मुझे यह जानकारी मिली:\n{$body}\n\nयदि चाहें तो मैं इसे कवरेज, कीमत या क्लेम के हिसाब से और स्पष्ट कर सकता हूँ।",
            'te' => "Petsfolio పత్రాల ఆధారంగా నాకు దగ్గరగా దొరికిన సమాచారం ఇది:\n{$body}\n\nకావాలంటే దీన్ని కవర్, ధర లేదా క్లెయిమ్ దృష్టిలో ఇంకా స్పష్టంగా చెబుతాను.",
            'kn' => "Petsfolio ದಾಖಲೆಗಳ ಆಧಾರದ ಮೇಲೆ ನನಗೆ ಸಿಕ್ಕ ಹತ್ತಿರದ ಮಾಹಿತಿ ಇದು:\n{$body}\n\nಬೇಕಾದರೆ ಇದನ್ನು ಕವರ್, ಬೆಲೆ ಅಥವಾ ಕ್ಲೈಮ್ ದೃಷ್ಟಿಯಿಂದ ಇನ್ನಷ್ಟು ಸ್ಪಷ್ಟಪಡಿಸಬಹುದು.",
            'ta' => "Petsfolio ஆவணங்களின் அடிப்படையில் எனக்கு கிடைத்த நெருக்கமான தகவல் இது:\n{$body}\n\nவிருப்பமிருந்தால் இதை கவர், விலை அல்லது க்ளெயிம் நோக்கில் மேலும் விளக்கலாம்.",
            default => "Based on the Petsfolio documents, here is the closest matching information:\n{$body}\n\nIf you want, I can narrow this down into coverage, pricing, or claim guidance.",
        };
    }

    private function buildChatTitle(string $message): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $message) ?? $message);
        return mb_strlen($title) > 56 ? mb_substr($title, 0, 53) . '...' : $title;
    }

    private function planName(array $plan, string $locale): string
    {
        return $locale === 'hi' ? (string) $plan['name_hi'] : (string) $plan['name_en'];
    }

    private function petLabel(string $petType, string $locale): string
    {
        return match ($locale) {
            'hi' => $petType === 'cat' ? 'कैट' : 'डॉग',
            'te' => $petType === 'cat' ? 'పిల్లి' : 'కుక్క',
            'kn' => $petType === 'cat' ? 'ಬೆಕ್ಕು' : 'ನಾಯಿ',
            'ta' => $petType === 'cat' ? 'பூனை' : 'நாய்',
            default => $petType,
        };
    }

    private function singlePlanSource(array $plan, string $locale): array
    {
        return [
            'type'    => 'plan',
            'title'   => $this->planName($plan, $locale),
            'snippet' => $locale === 'hi' ? (string) $plan['summary_hi'] : (string) $plan['summary_en'],
        ];
    }

    private function planSources(array $plans, string $locale): array
    {
        return array_map(fn(array $plan): array => $this->singlePlanSource($plan, $locale), array_slice($plans, 0, 2));
    }

    private function chunkSources(array $chunks): array
    {
        return array_map(static fn(array $chunk): array => [
            'type'    => 'document',
            'title'   => $chunk['title'] ?? 'Insurance Document',
            'snippet' => $chunk['snippet'] ?? '',
        ], array_slice($chunks, 0, 2));
    }

    private function toHistoryRoles(array $messages): array
    {
        return array_map(static fn(array $message): array => [
            'role'    => $message['sender'] === 'assistant' ? 'assistant' : 'user',
            'content' => $message['message'],
        ], $messages);
    }

    private function toDocumentContextBlocks(array $chunks): array
    {
        return array_map(static fn(array $chunk): array => [
            'title'   => $chunk['title'] ?? 'Insurance Source',
            'content' => $chunk['content'] ?? '',
        ], array_slice($chunks, 0, $this->config->retrievalResultLimit));
    }

    private function toPlanContextBlocks(array $plans, string $locale): array
    {
        $blocks = [];

        foreach (array_slice($plans, 0, 6) as $plan) {
            $summary = $locale === 'hi' ? (string) $plan['summary_hi'] : (string) $plan['summary_en'];
            $claimSteps = $locale === 'hi' ? (string) $plan['claim_steps_hi'] : (string) $plan['claim_steps_en'];
            $exclusions = $locale === 'hi' ? (string) $plan['exclusions_hi'] : (string) $plan['exclusions_en'];

            $blocks[] = [
                'title'   => 'Plan: ' . $this->planName($plan, $locale),
                'content' => implode("\n", [
                    'Pet type: ' . (string) $plan['pet_type'],
                    'Summary: ' . $summary,
                    'Monthly price: $' . number_format((float) $plan['price_monthly'], 2),
                    'Annual limit: $' . number_format((int) $plan['annual_limit']),
                    'Deductible: $' . number_format((int) $plan['deductible']),
                    'Reimbursement: ' . (int) $plan['reimbursement_percent'] . '%',
                    'Waiting period: ' . (int) $plan['waiting_period_days'] . ' days',
                    'Claim steps: ' . $claimSteps,
                    'Exclusions: ' . $exclusions,
                ]),
            ];
        }

        return $blocks;
    }
}
