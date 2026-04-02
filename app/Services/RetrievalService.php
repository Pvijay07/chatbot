<?php

namespace App\Services;

use App\Models\InsuranceDocumentChunkModel;
use CodeIgniter\Cache\CacheInterface;
use Config\Petsfolio;

class RetrievalService
{
    private InsuranceDocumentChunkModel $chunkModel;
    private ?CacheInterface $cache = null;

    public function __construct(private readonly Petsfolio $config = new Petsfolio())
    {
        $this->chunkModel = new InsuranceDocumentChunkModel();
        try {
            $this->cache = cache();
        } catch (\Throwable $exception) {
            log_message('warning', 'Petsfolio retrieval cache disabled: ' . $exception->getMessage());
            $this->cache = null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRelevantChunks(string $query, string $locale = 'en', ?int $limit = null): array
    {
        $limit ??= $this->config->retrievalResultLimit;
        $originalNormalized = $this->normalize($query);
        $expandedQuery = $this->expandQuery($query);
        $normalizedQuery = $this->normalize($expandedQuery);
        $cacheKey = 'petsfolio-rag-' . md5($locale . '|' . $limit . '|' . $normalizedQuery);
        $cached = $this->cache?->get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $terms = $this->extractTerms($normalizedQuery);
        $chunks = $this->chunkModel
            ->select('insurance_document_chunks.*, insurance_documents.title, insurance_documents.file_name')
            ->join('insurance_documents', 'insurance_documents.id = insurance_document_chunks.document_id')
            ->where('insurance_documents.is_active', 1)
            ->findAll();

        $scored = [];
        foreach ($chunks as $chunk) {
            $score = $this->scoreChunk($chunk, $terms, $originalNormalized, $normalizedQuery, $locale);
            if ($score < 10) {
                continue;
            }

            $chunk['score'] = $score;
            $chunk['snippet'] = $this->snippet((string) ($chunk['content'] ?? ''));
            $scored[] = $chunk;
        }

        usort($scored, static fn(array $left, array $right): int => $right['score'] <=> $left['score']);

        $results = array_values(array_slice($scored, 0, $limit));
        $this->cache?->save($cacheKey, $results, $this->config->cacheTtl);

        return $results;
    }

    public function storeDocumentChunks(int $documentId, string $text, string $language = 'en'): void
    {
        $chunks = $this->chunkText($text);
        foreach ($chunks as $index => $chunk) {
            $normalized = $this->normalize($chunk);
            $terms = $this->extractTerms($normalized);

            $this->chunkModel->insert([
                'document_id' => $documentId,
                'chunk_index' => $index,
                'language'    => $language,
                'content'     => $chunk,
                'token_count' => count($terms),
                'keywords'    => implode(', ', $terms),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function chunkText(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', preg_replace("/\r\n?/", "\n", trim($text)) ?? '') ?: [];
        $chunks = [];
        $buffer = '';
        $chunkSize = $this->config->retrievalChunkSize;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($buffer . "\n\n" . $paragraph) > $chunkSize && $buffer !== '') {
                $chunks[] = $buffer;
                $buffer = mb_substr($buffer, max(0, mb_strlen($buffer) - $this->config->retrievalChunkOverlap));
            }

            $buffer = trim($buffer . "\n\n" . $paragraph);
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    private function scoreChunk(array $chunk, array $terms, string $originalQuery, string $fullExpandedQuery, string $locale): int
    {
        $content = $this->normalize(
            (string) ($chunk['content'] ?? '') . ' ' .
            (string) ($chunk['keywords'] ?? '') . ' ' .
            (string) ($chunk['title'] ?? '')
        );

        if ($originalQuery === '' || $content === '') {
            return 0;
        }

        $score = 0;
        $matchedTerms = 0;
        $matchedSignals = 0;

        if ($originalQuery !== '' && str_contains($content, $originalQuery)) {
            $score += 40;
            $matchedTerms += 2;
        } elseif ($fullExpandedQuery !== '' && str_contains($content, $fullExpandedQuery)) {
            $score += 25;
            $matchedTerms += 1;
        }

        foreach ($terms as $term) {
            if (str_contains($content, $term)) {
                $matchedTerms++;
                $score += mb_strlen($term) >= 6 ? 9 : 6;
            }
        }

        foreach ($this->signalTerms() as $signal) {
            if (str_contains($fullExpandedQuery, $signal) && str_contains($content, $signal)) {
                $matchedSignals++;
                $score += 10;
            }
        }

        if ($matchedTerms === 0 && $matchedSignals === 0) {
            return 0;
        }

        if (($chunk['language'] ?? 'en') === $locale) {
            $score += 8;
        }

        return $score;
    }

    /**
     * @return list<string>
     */
    private function extractTerms(string $normalizedText): array
    {
        $stopWords = [
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'your', 'what', 'does', 'have',
            'tell', 'about', 'into', 'after', 'before', 'only', 'plan', 'plans', 'policy', 'pet',
            'name', 'who', 'where', 'when', 'why', 'how', 'are', 'you',
            'की', 'का', 'के', 'और', 'यह', 'क्या', 'में', 'से', 'पर', 'एक', 'को', 'है',
            'ఇది', 'అది', 'మరి', 'కి', 'లో', 'పై',
            'ಇದು', 'ಅದು', 'ಮತ್ತು', 'ನಲ್ಲಿ', 'ಗೆ',
            'இது', 'அது', 'மேலும்', 'என்ன', 'க்கு', 'இல்',
        ];

        $terms = preg_split('/\s+/', $normalizedText) ?: [];
        $result = [];

        foreach ($terms as $term) {
            if ($term === '' || in_array($term, $stopWords, true) || mb_strlen($term) < 2) {
                continue;
            }

            $result[$term] = true;
        }

        return array_keys($result);
    }

    private function expandQuery(string $query): string
    {
        $map = [
            'क्लेम' => 'claim reimbursement',
            'दावा' => 'claim reimbursement',
            'बीमा' => 'insurance policy coverage',
            'पॉलिसी' => 'policy coverage',
            'कवर' => 'coverage',
            'कीमत' => 'price premium cost',
            'प्रीमियम' => 'premium price',
            'डॉग' => 'dog canine',
            'कुत्ता' => 'dog canine',
            'कैट' => 'cat feline',
            'बिल्ली' => 'cat feline',
            'इलाज' => 'treatment illness',
            'रीइम्बर्समेंट' => 'reimbursement refund',
            'క్లెయిమ్' => 'claim reimbursement',
            'బీమా' => 'insurance policy coverage',
            'పాలసీ' => 'policy coverage',
            'కవర్' => 'coverage',
            'ధర' => 'price premium cost',
            'ప్రీమియం' => 'premium price',
            'కుక్క' => 'dog canine',
            'పిల్లి' => 'cat feline',
            'చికిత్స' => 'treatment illness',
            'రీయింబర్స్‌మెంట్' => 'reimbursement refund',
            'ಕ್ಲೈಮ್' => 'claim reimbursement',
            'ವಿಮೆ' => 'insurance policy coverage',
            'ಪಾಲಿಸಿ' => 'policy coverage',
            'ಕವರ್' => 'coverage',
            'ಬೆಲೆ' => 'price premium cost',
            'ಪ್ರೀಮಿಯಂ' => 'premium price',
            'ನಾಯಿ' => 'dog canine',
            'ಬೆಕ್ಕು' => 'cat feline',
            'ಚಿಕಿತ್ಸೆ' => 'treatment illness',
            'ಮರುಪಾವತಿ' => 'reimbursement refund',
            'க்ளெயிம்' => 'claim reimbursement',
            'காப்பீடு' => 'insurance policy coverage',
            'பாலிசி' => 'policy coverage',
            'கவர்' => 'coverage',
            'விலை' => 'price premium cost',
            'பிரீமியம்' => 'premium price',
            'நாய்' => 'dog canine',
            'பூனை' => 'cat feline',
            'சிகிச்சை' => 'treatment illness',
            'இழப்பீடு' => 'reimbursement refund',
        ];

        $expanded = $query;
        foreach ($map as $needle => $replacement) {
            if (mb_stripos($expanded, $needle) !== false) {
                $expanded .= ' ' . $replacement;
            }
        }

        return $expanded;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function snippet(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        return mb_strlen($text) > 280 ? mb_substr($text, 0, 277) . '...' : $text;
    }

    /**
     * @return list<string>
     */
    private function signalTerms(): array
    {
        return [
            'claim', 'pricing', 'price', 'premium', 'coverage', 'dog', 'cat',
            'क्लेम', 'कीमत', 'कवर', 'डॉग', 'कैट',
            'క్లెయిమ్', 'ధర', 'కవర్', 'కుక్క', 'పిల్లి',
            'ಕ್ಲೈಮ್', 'ಬೆಲೆ', 'ಕವರ್', 'ನಾಯಿ', 'ಬೆಕ್ಕು',
            'க்ளெயிம்', 'விலை', 'கவர்', 'நாய்', 'பூனை',
        ];
    }
}
