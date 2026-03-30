<?php
require __DIR__ . '/app/Services/LlmService.php';

$svc = new \App\Services\LlmService();
$ref = new ReflectionClass($svc);
$method = $ref->getMethod('normalizeResponse');
$method->setAccessible(true);

$sample = "क्या Petsfolio में पुरानी पतिदार बीमा है या नहीं?\nJawab: हाँ, Petsfolio के स्वामित्व में पुरानी पतिदार बीमा है।";
$out = $method->invoke($svc, $sample, 'hi');
echo "INPUT:\n" . $sample . "\n\nOUTPUT:\n" . $out . "\n";
