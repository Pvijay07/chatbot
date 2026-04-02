<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/import_policy_pdf.php <pdf-path> [language] [title]\n");
    exit(1);
}

$sourcePath = $argv[1];
$language = $argv[2] ?? 'en';
$title = $argv[3] ?? pathinfo($sourcePath, PATHINFO_FILENAME);
$root = dirname(__DIR__);
$env = parseEnvFile($root . '/.env');

$db = new mysqli(
    $env['database.default.hostname'] ?? 'localhost',
    $env['database.default.username'] ?? 'root',
    $env['database.default.password'] ?? '',
    $env['database.default.database'] ?? '',
    (int) ($env['database.default.port'] ?? 3306)
);

if ($db->connect_errno) {
    fwrite(STDERR, "Database connection failed: {$db->connect_error}\n");
    exit(1);
}

$db->set_charset('utf8mb4');

if (!is_file($sourcePath)) {
    fwrite(STDERR, "PDF not found: {$sourcePath}\n");
    exit(1);
}

$parser = new \Smalot\PdfParser\Parser();
$document = $parser->parseFile($sourcePath);
$text = trim($document->getText());

if ($text === '') {
    fwrite(STDERR, "No readable text extracted from PDF.\n");
    exit(1);
}

$destDir = $root . '/writable/uploads/insurance';
if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
    fwrite(STDERR, "Unable to create destination directory.\n");
    exit(1);
}

$safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]+/', '-', basename($sourcePath));
$storedPath = $destDir . '/' . $safeName;
if (!copy($sourcePath, $storedPath)) {
    fwrite(STDERR, "Unable to copy PDF into writable storage.\n");
    exit(1);
}

$contentHash = hash_file('sha256', $storedPath);
$uploadedBy = firstAdminUserId($db);
$documentId = upsertDocument($db, [
    'title' => $title,
    'file_name' => basename($sourcePath),
    'file_path' => $storedPath,
    'mime_type' => 'application/pdf',
    'language' => $language,
    'content_hash' => $contentHash,
    'uploaded_by' => $uploadedBy,
]);

deleteChunks($db, $documentId);
$chunks = chunkText($text, (int) ($env['petsfolio.retrievalChunkSize'] ?? 750), (int) ($env['petsfolio.retrievalChunkOverlap'] ?? 120));

$insert = $db->prepare(
    'INSERT INTO insurance_document_chunks (document_id, chunk_index, language, content, token_count, keywords, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
);

if (!$insert) {
    fwrite(STDERR, "Unable to prepare chunk insert statement.\n");
    exit(1);
}

$now = date('Y-m-d H:i:s');
foreach ($chunks as $index => $chunk) {
    $normalized = normalize($chunk);
    $terms = extractTerms($normalized);
    $tokenCount = count($terms);
    $keywords = implode(', ', $terms);
    $insert->bind_param('iississ', $documentId, $index, $language, $chunk, $tokenCount, $keywords, $now);
    $insert->execute();
}

$insert->close();
$db->close();

echo "Imported document #{$documentId}\n";
echo "Title: {$title}\n";
echo "Language: {$language}\n";
echo "Chunks: " . count($chunks) . "\n";

function parseEnvFile(string $path): array
{
    $values = [];
    if (!is_file($path)) {
        return $values;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        $values[$key] = $value;
    }

    return $values;
}

function firstAdminUserId(mysqli $db): ?int
{
    $result = $db->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if (!$result) {
        return null;
    }

    $row = $result->fetch_assoc();
    return $row ? (int) $row['id'] : null;
}

function upsertDocument(mysqli $db, array $document): int
{
    $select = $db->prepare('SELECT id FROM insurance_documents WHERE content_hash = ? LIMIT 1');
    $select->bind_param('s', $document['content_hash']);
    $select->execute();
    $result = $select->get_result();
    $existing = $result ? $result->fetch_assoc() : null;
    $select->close();

    $now = date('Y-m-d H:i:s');

    if ($existing) {
        $documentId = (int) $existing['id'];
        $update = $db->prepare('UPDATE insurance_documents SET title = ?, file_name = ?, file_path = ?, mime_type = ?, language = ?, uploaded_by = ?, is_active = 1, updated_at = ? WHERE id = ?');
        $update->bind_param(
            'sssssisi',
            $document['title'],
            $document['file_name'],
            $document['file_path'],
            $document['mime_type'],
            $document['language'],
            $document['uploaded_by'],
            $now,
            $documentId
        );
        $update->execute();
        $update->close();

        return $documentId;
    }

    $insert = $db->prepare('INSERT INTO insurance_documents (title, file_name, file_path, mime_type, language, content_hash, uploaded_by, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
    $insert->bind_param(
        'ssssssiss',
        $document['title'],
        $document['file_name'],
        $document['file_path'],
        $document['mime_type'],
        $document['language'],
        $document['content_hash'],
        $document['uploaded_by'],
        $now,
        $now
    );
    $insert->execute();
    $documentId = (int) $insert->insert_id;
    $insert->close();

    return $documentId;
}

function deleteChunks(mysqli $db, int $documentId): void
{
    $delete = $db->prepare('DELETE FROM insurance_document_chunks WHERE document_id = ?');
    $delete->bind_param('i', $documentId);
    $delete->execute();
    $delete->close();
}

function chunkText(string $text, int $chunkSize, int $chunkOverlap): array
{
    $paragraphs = preg_split('/\n\s*\n/', preg_replace("/\r\n?/", "\n", trim($text)) ?? '') ?: [];
    $chunks = [];
    $buffer = '';

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }

        if (mb_strlen($buffer . "\n\n" . $paragraph) > $chunkSize && $buffer !== '') {
            $chunks[] = $buffer;
            $buffer = mb_substr($buffer, max(0, mb_strlen($buffer) - $chunkOverlap));
        }

        $buffer = trim($buffer . "\n\n" . $paragraph);
    }

    if ($buffer !== '') {
        $chunks[] = $buffer;
    }

    return $chunks;
}

function normalize(string $text): string
{
    $text = mb_strtolower($text);
    $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
    return trim(preg_replace('/\s+/', ' ', $text) ?? '');
}

function extractTerms(string $normalizedText): array
{
    $stopWords = [
        'the', 'and', 'for', 'with', 'that', 'this', 'from', 'your', 'what', 'does', 'have',
        'tell', 'about', 'into', 'after', 'before', 'only', 'plan', 'plans', 'policy', 'pet',
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
