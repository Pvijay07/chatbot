<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\DocumentService;
use App\Services\LlmService;

class DocumentController extends BaseController
{
    /**
     * Handle document upload and return extracted text along with initial analysis
     * 
     * POST /api/document/upload
     * Form data:
     *   - file: uploaded file (TXT, PDF, DOCX, JSON)
     *   - question: optional prompt about the document
     * 
     * Response: { success: true, text: "extracted text", analysis: "optional analysis", or error details }
     */
    public function upload()
    {
        $file = $this->request->getFile('file');

        if (!$file || !$file->isValid()) {
            return $this->respond([
                'error' => true,
                'message' => 'File upload failed or no file provided'
            ], 400);
        }

        // Validate file size (max 10 MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->respond([
                'error' => true,
                'message' => 'File too large. Maximum size is 10 MB.'
            ], 400);
        }

        try {
            // Move file to temporary location
            $tempDir = WRITEPATH . 'uploads';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            $tempFile = $tempDir . '/' . uniqid('doc_') . '_' . $file->getName();
            if (!$file->move($tempDir, basename($tempFile))) {
                throw new \Exception('Failed to save uploaded file');
            }

            // Extract text from document
            $docService = new DocumentService();
            $extractedText = $docService->extractText($tempFile);

            // Optionally analyze the document if a question was provided
            $analysis = null;
            $analysisError = null;
            $question = trim($this->request->getPost('question') ?? '');
            if (!empty($question)) {
                try {
                    // Get user's locale from session only (remove test POST override)
                    $locale = 'en';
                    try {
                        $sess = session();
                        $locale = $sess->get('locale') ?? 'en';
                    } catch (\Throwable $_) {}

                    // Lightweight heuristic: if the question does not mention insurance-related keywords,
                    // return the canonical localized refusal without calling the LLM. This prevents
                    // off-topic answers from the LLM and enforces policy at the application level.
                    $isInsurance = (bool) preg_match('/\b(insur|policy|claim|coverage|premium|vet|veterin|reimburse|deduct|copay|quote|underwrit|exclusion)\b/i', $question);

                    if (!$isInsurance) {
                        $llm = service('llm');
                        $analysis = $llm->refusal($locale);
                    } else {
                        $llm = service('llm');
                        $prompt = "Analyze this document and answer the following question. Focus on insurance-related information if present.\n\nDocument:\n" . substr($extractedText, 0, 5000) . "\n\nQuestion: " . $question;
                        $analysis = $llm->ask($prompt, $locale);
                    }
                } catch (\Exception $e) {
                    // LLM service error - log but continue with extracted text
                    $analysisError = 'Analysis unavailable: ' . $e->getMessage();
                }
            }

            // Clean up temp file
            @unlink($tempFile);

            $response = [
                'success' => true,
                'text' => $extractedText,
                'filename' => $file->getName(),
                'size' => strlen($extractedText)
            ];
            
            if ($analysis) {
                $response['analysis'] = $analysis;
            } elseif ($analysisError) {
                $response['analysis_error'] = $analysisError;
            }

            return $this->respond($response);

        } catch (\Exception $e) {
            // Clean up on error
            @unlink($tempFile ?? null);

            return $this->respond([
                'error' => true,
                'message' => 'Document processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
