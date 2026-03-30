<?php

namespace App\Services;

class DocParserService
{
    public function parse($file)
    {
        $ext = strtolower($file->getExtension());

        if ($ext === 'pdf') {
            $parser = new \Smalot\PdfParser\Parser();
            return $parser->parseFile($file->getTempName())->getText();
        }

        return shell_exec("tesseract " . $file->getTempName() . " stdout");
    }
}