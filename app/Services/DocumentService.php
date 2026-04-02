<?php

namespace App\Services;

use App\Models\InsuranceDocumentModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class DocumentService
{
    private InsuranceDocumentModel $documentModel;

    public function __construct()
    {
        $this->documentModel = new InsuranceDocumentModel();
    }

    public function extractText(string $filePath): string
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException('Uploaded file was not found.');
        }

        return match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'txt', 'md' => $this->extractFromText($filePath),
            'json'      => $this->extractFromJson($filePath),
            'pdf'       => $this->extractFromPdf($filePath),
            'docx'      => $this->extractFromDocx($filePath),
            default     => throw new \RuntimeException('Unsupported document format.'),
        };
    }

    public function storeUploadedDocument(UploadedFile $file, int $uploadedBy, string $language = 'en'): array
    {
        if (!$file->isValid()) {
            throw new \RuntimeException($file->getErrorString() ?: 'Invalid uploaded file.');
        }

        $directory = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'insurance';
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create upload directory.');
        }

        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]+/', '-', $file->getClientName());
        $file->move($directory, $safeName, true);

        $storedPath = $directory . DIRECTORY_SEPARATOR . $safeName;
        $text = $this->extractText($storedPath);

        $documentId = $this->documentModel->insert([
            'title'        => pathinfo($file->getClientName(), PATHINFO_FILENAME),
            'file_name'    => $file->getClientName(),
            'file_path'    => $storedPath,
            'mime_type'    => $file->getMimeType(),
            'language'     => in_array($language, ['en', 'hi', 'te', 'kn', 'ta'], true) ? $language : 'en',
            'content_hash' => hash_file('sha256', $storedPath),
            'uploaded_by'  => $uploadedBy,
            'is_active'    => 1,
        ], true);

        service('retrieval')->storeDocumentChunks((int) $documentId, $text, $language);

        return [
            'id'         => (int) $documentId,
            'title'      => pathinfo($file->getClientName(), PATHINFO_FILENAME),
            'file_name'  => $file->getClientName(),
            'language'   => $language,
            'text'       => $text,
            'file_path'  => $storedPath,
            'created_at' => date('c'),
        ];
    }

    private function extractFromText(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Unable to read text document.');
        }

        return trim($content);
    }

    private function extractFromJson(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('Unable to read JSON document.');
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON document.');
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function extractFromPdf(string $filePath): string
    {
        $parser = new \Smalot\PdfParser\Parser();
        $document = $parser->parseFile($filePath);
        $text = trim($document->getText());

        if ($text === '') {
            throw new \RuntimeException('No readable PDF text was extracted.');
        }

        return $text;
    }

    private function extractFromDocx(string $filePath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Unable to open DOCX document.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('Unable to read DOCX contents.');
        }

        $xml = str_replace(['</w:p>', '</w:tr>', '</w:tc>'], ["\n", "\n", ' '], $xml);
        $text = trim(strip_tags($xml));

        if ($text === '') {
            throw new \RuntimeException('No readable DOCX text was extracted.');
        }

        return $text;
    }
}
