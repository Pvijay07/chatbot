<?php

namespace App\Services;

class DocumentService
{
    /**
     * Extract text from uploaded file (TXT, PDF, DOCX, JSON)
     * 
     * @param string $filePath Absolute path to uploaded file
     * @return string Extracted text content
     * @throws \Exception If file type unsupported or extraction fails
     */
    public function extractText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception('File not found: ' . $filePath);
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'txt' => $this->extractFromTxt($filePath),
            'pdf' => $this->extractFromPdf($filePath),
            'docx' => $this->extractFromDocx($filePath),
            'json' => $this->extractFromJson($filePath),
            default => throw new \Exception('Unsupported file type: ' . $ext)
        };
    }

    private function extractFromTxt(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception('Failed to read TXT file');
        }
        return trim($content);
    }

    private function extractFromPdf(string $filePath): string
    {
        // Use smalot/pdfparser to extract text from PDF
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            
            // Extract text from all pages
            $pages = $pdf->getPages();
            $text = [];
            
            foreach ($pages as $page) {
                $pageText = $page->getText();
                if (!empty($pageText)) {
                    $text[] = $pageText;
                }
            }
            
            $extracted = trim(implode("\n\n", $text));
            if (empty($extracted)) {
                throw new \Exception('No text could be extracted from PDF');
            }
            
            return $extracted;
        } catch (\Exception $e) {
            throw new \Exception('Failed to extract PDF text: ' . $e->getMessage());
        }
    }

    private function extractFromDocx(string $filePath): string
    {
        // DOCX is a ZIP archive containing XML files
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \Exception('Failed to open DOCX file');
        }

        // Read document.xml which contains the main text
        $xmlContent = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xmlContent === false) {
            throw new \Exception('Failed to extract document.xml from DOCX');
        }

        // Parse XML and extract text
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            throw new \Exception('Failed to parse DOCX XML');
        }

        // Register namespace
        $namespaces = $xml->getNamespaces(true);
        $w = $namespaces['w'] ?? 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

        // Extract all text nodes
        $text = [];
        foreach ($xml->xpath('//w:t', $namespaces) as $t) {
            $text[] = (string) $t;
        }

        return trim(implode('', $text));
    }

    private function extractFromJson(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception('Failed to read JSON file');
        }

        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON: ' . json_last_error_msg());
        }

        // Convert JSON to readable text format
        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
