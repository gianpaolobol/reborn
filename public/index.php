<?php

declare(strict_types=1);


function reborn_json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function reborn_text_input(string $key, int $max = 2000): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if (strlen($value) > $max) {
        $value = substr($value, 0, $max);
    }
    return $value;
}


function reborn_json_input(): array
{
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function reborn_normalize_lookup_text(string $value): string
{
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function reborn_lookup_tokens(string $value): array
{
    $normalized = reborn_normalize_lookup_text($value);
    if ($normalized === '') {
        return [];
    }
    $tokens = preg_split('/\s+/', $normalized) ?: [];
    $tokens = array_values(array_filter(array_unique($tokens), static fn (string $token): bool => strlen($token) >= 3));
    return $tokens;
}

function reborn_text_from_mixed(mixed $value): string
{
    if (is_scalar($value) || $value === null) {
        return trim((string) $value);
    }
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            $text = reborn_text_from_mixed($item);
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        return trim(implode(' ', $parts));
    }
    return '';
}

function reborn_library_record_lookup_text(array $record): string
{
    $parts = [
        $record['title'] ?? '',
        $record['query'] ?? '',
        $record['source'] ?? '',
        $record['url'] ?? '',
        $record['criteria_json'] ?? '',
        $record['recognition_summary_json'] ?? '',
    ];
    return reborn_text_from_mixed($parts);
}

function reborn_library_signature(array $record): string
{
    $tokens = reborn_lookup_tokens(reborn_library_record_lookup_text($record));
    sort($tokens);
    return hash('sha256', implode('|', $tokens));
}

function reborn_decode_json_list(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function reborn_read_library_records(): array
{
    $jsonl = reborn_model_library_dir('contributions.jsonl');
    if (!is_file($jsonl)) {
        return [];
    }
    $records = [];
    $lines = file($jsonl, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $record = json_decode($line, true);
        if (is_array($record)) {
            $records[] = $record;
        }
    }
    return $records;
}


function reborn_model_library_stats_file(): string
{
    return reborn_model_library_dir('model_stats.json');
}

function reborn_read_model_library_stats(): array
{
    $file = reborn_model_library_stats_file();
    if (!is_file($file)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function reborn_write_model_library_stats(array $stats): void
{
    $base = reborn_model_library_dir();
    if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
        throw new RuntimeException('Impossibile creare la cartella libreria.');
    }
    file_put_contents(reborn_model_library_stats_file(), json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
}

function reborn_model_exists_in_library(string $id): bool
{
    foreach (reborn_read_library_records() as $record) {
        if ((string) ($record['id'] ?? '') === $id) {
            return true;
        }
    }
    return false;
}

function reborn_increment_model_library_stat(string $id, string $key): array
{
    $id = preg_replace('/[^a-zA-Z0-9_-]+/', '', $id) ?? '';
    if ($id === '' || !in_array($key, ['download_count', 'preference_count'], true)) {
        return ['download_count' => 0, 'preference_count' => 0];
    }
    $stats = reborn_read_model_library_stats();
    if (!isset($stats[$id]) || !is_array($stats[$id])) {
        $stats[$id] = ['download_count' => 0, 'preference_count' => 0];
    }
    $stats[$id]['download_count'] = (int) ($stats[$id]['download_count'] ?? 0);
    $stats[$id]['preference_count'] = (int) ($stats[$id]['preference_count'] ?? 0);
    $stats[$id][$key] += 1;
    $stats[$id]['updated_at'] = gmdate('c');
    reborn_write_model_library_stats($stats);
    return [
        'download_count' => (int) $stats[$id]['download_count'],
        'preference_count' => (int) $stats[$id]['preference_count'],
    ];
}


function reborn_increment_model_library_download_once(string $id, string $sessionId): array
{
    $id = preg_replace('/[^a-zA-Z0-9_-]+/', '', $id) ?? '';
    $sessionId = trim($sessionId);
    if ($id === '') {
        return ['download_count' => 0, 'preference_count' => 0, 'counted' => false];
    }

    if ($sessionId === '') {
        $sessionId = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . gmdate('Y-m-d'));
    }

    $sessionHash = hash('sha256', $sessionId);
    $stats = reborn_read_model_library_stats();
    if (!isset($stats[$id]) || !is_array($stats[$id])) {
        $stats[$id] = ['download_count' => 0, 'preference_count' => 0, 'download_sessions' => []];
    }

    $stats[$id]['download_count'] = (int) ($stats[$id]['download_count'] ?? 0);
    $stats[$id]['preference_count'] = (int) ($stats[$id]['preference_count'] ?? 0);
    $sessions = is_array($stats[$id]['download_sessions'] ?? null) ? $stats[$id]['download_sessions'] : [];
    $counted = false;

    if (!isset($sessions[$sessionHash])) {
        $sessions[$sessionHash] = gmdate('c');
        $stats[$id]['download_count'] += 1;
        $stats[$id]['download_sessions'] = $sessions;
        $stats[$id]['updated_at'] = gmdate('c');
        $counted = true;
        reborn_write_model_library_stats($stats);
    }

    return [
        'download_count' => (int) $stats[$id]['download_count'],
        'preference_count' => (int) $stats[$id]['preference_count'],
        'counted' => $counted,
    ];
}

function reborn_model_library_stats_for(string $id): array
{
    $stats = reborn_read_model_library_stats();
    if (!is_array($stats[$id] ?? null)) {
        return ['download_count' => 0, 'preference_count' => 0];
    }
    return [
        'download_count' => (int) ($stats[$id]['download_count'] ?? 0),
        'preference_count' => (int) ($stats[$id]['preference_count'] ?? 0),
    ];
}

function reborn_enrich_library_match_stats(array $match): array
{
    $stats = reborn_model_library_stats_for((string) ($match['id'] ?? ''));
    $match['download_count'] = (int) ($stats['download_count'] ?? 0);
    $match['preference_count'] = (int) ($stats['preference_count'] ?? 0);
    return $match;
}

function reborn_ensure_model_library_schema(PDO $pdo): void
{
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info(model_library_contributions)') ?: [] as $row) {
        $columns[(string) $row['name']] = true;
    }
    $add = [
        'repair_case_id' => 'TEXT NOT NULL DEFAULT ""',
        'attachment_ids_json' => 'TEXT NOT NULL DEFAULT "[]"',
        'image_fingerprints_json' => 'TEXT NOT NULL DEFAULT "[]"',
        'lookup_text' => 'TEXT NOT NULL DEFAULT ""',
        'lookup_signature' => 'TEXT NOT NULL DEFAULT ""',
    ];
    foreach ($add as $name => $definition) {
        if (!isset($columns[$name])) {
            $pdo->exec('ALTER TABLE model_library_contributions ADD COLUMN ' . $name . ' ' . $definition);
        }
    }
}

function reborn_model_library_dir(string $child = ''): string
{
    $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'model_library';
    return $child === '' ? $base : $base . DIRECTORY_SEPARATOR . $child;
}

function reborn_store_model_file(string $id): ?array
{
    if (!isset($_FILES['model_file']) || !is_array($_FILES['model_file'])) {
        return null;
    }

    $file = $_FILES['model_file'];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Caricamento file non completato.');
    }

    $size = (int) ($file['size'] ?? 0);
    $maxBytes = 80 * 1024 * 1024;
    if ($size <= 0 || $size > $maxBytes) {
        throw new RuntimeException('Il file modello supera il limite di 80 MB.');
    }

    $originalName = (string) ($file['name'] ?? 'model.stl');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['stl', '3mf', 'obj', 'step', 'stp', 'zip'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('Formato modello non supportato. Usa STL, 3MF, OBJ, STEP/STP o ZIP.');
    }

    $day = gmdate('Y-m-d');
    $targetDir = reborn_model_library_dir('uploads' . DIRECTORY_SEPARATOR . $day);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Impossibile creare la cartella upload.');
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', basename($originalName)) ?: ('model.' . $extension);
    $storedName = $id . '-' . $safeName;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $targetPath)) {
        throw new RuntimeException('Impossibile salvare il file modello.');
    }

    return [
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'extension' => $extension,
        'size_bytes' => $size,
        'sha256' => hash_file('sha256', $targetPath) ?: '',
        'storage_path' => 'storage/model_library/uploads/' . $day . '/' . $storedName,
    ];
}

function reborn_save_library_record(array $record): array
{
    $base = reborn_model_library_dir();
    if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
        throw new RuntimeException('Impossibile creare la cartella libreria.');
    }

    $storedIn = [];
    $jsonl = $base . DIRECTORY_SEPARATOR . 'contributions.jsonl';
    file_put_contents($jsonl, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    $storedIn[] = 'jsonl';

    if (class_exists(PDO::class) && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $dbDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $dbDir . DIRECTORY_SEPARATOR . 'model_library_contributions.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS model_library_contributions (
            id TEXT PRIMARY KEY,
            contribution_type TEXT NOT NULL,
            source TEXT NOT NULL DEFAULT "",
            title TEXT NOT NULL DEFAULT "",
            url TEXT NOT NULL DEFAULT "",
            license TEXT NOT NULL DEFAULT "",
            query TEXT NOT NULL DEFAULT "",
            notes TEXT NOT NULL DEFAULT "",
            criteria_json TEXT NOT NULL DEFAULT "[]",
            recognition_summary_json TEXT NOT NULL DEFAULT "{}",
            repair_case_id TEXT NOT NULL DEFAULT "",
            attachment_ids_json TEXT NOT NULL DEFAULT "[]",
            image_fingerprints_json TEXT NOT NULL DEFAULT "[]",
            lookup_text TEXT NOT NULL DEFAULT "",
            lookup_signature TEXT NOT NULL DEFAULT "",
            file_json TEXT NOT NULL DEFAULT "{}",
            status TEXT NOT NULL DEFAULT "pending_review",
            created_at TEXT NOT NULL
        )');
        reborn_ensure_model_library_schema($pdo);
        $stmt = $pdo->prepare('INSERT INTO model_library_contributions (id, contribution_type, source, title, url, license, query, notes, criteria_json, recognition_summary_json, repair_case_id, attachment_ids_json, image_fingerprints_json, lookup_text, lookup_signature, file_json, status, created_at) VALUES (:id, :contribution_type, :source, :title, :url, :license, :query, :notes, :criteria_json, :recognition_summary_json, :repair_case_id, :attachment_ids_json, :image_fingerprints_json, :lookup_text, :lookup_signature, :file_json, :status, :created_at)');
        $stmt->execute([
            ':id' => $record['id'],
            ':contribution_type' => $record['contribution_type'],
            ':source' => $record['source'],
            ':title' => $record['title'],
            ':url' => $record['url'],
            ':license' => $record['license'],
            ':query' => $record['query'],
            ':notes' => $record['notes'],
            ':criteria_json' => $record['criteria_json'],
            ':recognition_summary_json' => $record['recognition_summary_json'],
            ':repair_case_id' => $record['repair_case_id'],
            ':attachment_ids_json' => $record['attachment_ids_json'],
            ':image_fingerprints_json' => $record['image_fingerprints_json'],
            ':lookup_text' => $record['lookup_text'],
            ':lookup_signature' => $record['lookup_signature'],
            ':file_json' => json_encode($record['file'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status' => $record['status'],
            ':created_at' => $record['created_at'],
        ]);
        $storedIn[] = 'sqlite';
    }

    return $storedIn;
}

function reborn_handle_model_library_contribution(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        reborn_json_response(405, ['message' => 'Metodo non consentito.']);
        return;
    }

    try {
        $id = 'mlc_' . bin2hex(random_bytes(12));
        $url = reborn_text_input('url', 2000);
        $source = reborn_text_input('source', 120);
        $title = reborn_text_input('title', 240);
        $file = reborn_store_model_file($id);

        if ($file === null) {
            reborn_json_response(422, ['message' => 'Carica il file del modello 3D da verificare e associare alla libreria Re-born.']);
            return;
        }
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            reborn_json_response(422, ['message' => 'Il link indicato non sembra valido.']);
            return;
        }

        $criteriaJson = reborn_text_input('criteria_json', 12000) ?: '[]';
        $recognitionSummaryJson = reborn_text_input('recognition_summary_json', 30000) ?: '{}';
        $attachmentIdsJson = reborn_text_input('attachment_ids_json', 12000) ?: '[]';
        $imageFingerprintsJson = reborn_text_input('image_fingerprints_json', 30000) ?: '[]';

        $record = [
            'id' => $id,
            'contribution_type' => reborn_text_input('contribution_type', 60) ?: ($file ? ($url === '' ? 'file' : 'link_and_file') : 'link'),
            'source' => $source,
            'title' => $title,
            'url' => $url,
            'license' => reborn_text_input('license', 160),
            'query' => reborn_text_input('query', 2000),
            'notes' => reborn_text_input('notes', 3000),
            'criteria_json' => $criteriaJson,
            'recognition_summary_json' => $recognitionSummaryJson,
            'repair_case_id' => reborn_text_input('repair_case_id', 120),
            'attachment_ids_json' => $attachmentIdsJson,
            'image_fingerprints_json' => $imageFingerprintsJson,
            'lookup_text' => '',
            'lookup_signature' => '',
            'file' => $file,
            'status' => 'pending_review',
            'created_at' => gmdate('c'),
            'remote_addr_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
        ];

        $record['lookup_text'] = reborn_library_record_lookup_text($record);
        $record['lookup_signature'] = reborn_library_signature($record);

        $storedIn = reborn_save_library_record($record);

        reborn_json_response(201, [
            'status' => 'accepted_for_review',
            'contribution_id' => $id,
            'stored_in' => $storedIn,
            'contribution' => [
                'id' => $id,
                'source' => $source,
                'title' => $title,
                'url' => $url,
                'file_name' => $file['original_name'] ?? '',
                'query' => $record['query'],
                'download_url' => '/api/v1/model-library/files/' . rawurlencode($id),
                'status' => 'pending_review',
            ],
        ]);
    } catch (Throwable $e) {
        reborn_json_response(500, ['message' => 'Verifica del modello 3D non avviata. Riprova o invia richiesta assistita.']);
    }
}

function reborn_handle_model_library_matches(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        reborn_json_response(405, ['message' => 'Metodo non consentito.']);
        return;
    }

    $input = reborn_json_input();
    $queryText = reborn_text_from_mixed([
        $input['query'] ?? '',
        $input['criteria'] ?? [],
        $input['recognition_summary'] ?? [],
    ]);
    $queryTokens = reborn_lookup_tokens($queryText);
    $imageHashes = [];
    foreach (($input['image_fingerprints'] ?? []) as $fingerprint) {
        if (is_array($fingerprint) && !empty($fingerprint['sha256'])) {
            $imageHashes[] = (string) $fingerprint['sha256'];
        }
    }
    $imageHashes = array_values(array_unique(array_filter($imageHashes)));

    $matches = [];
    foreach (reborn_read_library_records() as $record) {
        $recordTokens = reborn_lookup_tokens((string) ($record['lookup_text'] ?? '') ?: reborn_library_record_lookup_text($record));
        $overlap = count(array_intersect($queryTokens, $recordTokens));
        $score = 0;
        if (count($queryTokens) > 0) {
            $score = (int) round(min(90, ($overlap / max(1, count($queryTokens))) * 100));
        }

        $recordHashes = [];
        foreach (reborn_decode_json_list((string) ($record['image_fingerprints_json'] ?? '[]')) as $fingerprint) {
            if (is_array($fingerprint) && !empty($fingerprint['sha256'])) {
                $recordHashes[] = (string) $fingerprint['sha256'];
            }
        }
        if ($imageHashes && array_intersect($imageHashes, $recordHashes)) {
            $score = max($score, 98);
        }

        $codeBoost = false;
        foreach ($queryTokens as $token) {
            if (preg_match('/[a-z]+[0-9]+|[0-9]+[a-z]+/i', $token) && in_array($token, $recordTokens, true)) {
                $codeBoost = true;
                break;
            }
        }
        if ($codeBoost) {
            $score = max($score, 88);
        }

        if ($score < 45) {
            continue;
        }
        $file = is_array($record['file'] ?? null) ? $record['file'] : [];
        $matches[] = reborn_enrich_library_match_stats([
            'id' => (string) ($record['id'] ?? ''),
            'title' => (string) ($record['title'] ?? $record['query'] ?? 'Modello 3D in libreria'),
            'query' => (string) ($record['query'] ?? ''),
            'source' => (string) ($record['source'] ?? ''),
            'url' => (string) ($record['url'] ?? ''),
            'file_name' => (string) ($file['original_name'] ?? ''),
            'status' => (string) ($record['status'] ?? 'pending_review'),
            'match_score' => $score,
            'download_url' => !empty($file['storage_path']) ? '/api/v1/model-library/files/' . rawurlencode((string) ($record['id'] ?? '')) : '',
        ]);
    }

    usort($matches, static fn (array $a, array $b): int => ($b['match_score'] <=> $a['match_score']));
    reborn_json_response(200, ['matches' => array_slice($matches, 0, 10)]);
}

function reborn_handle_model_library_preference(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        reborn_json_response(405, ['message' => 'Metodo non consentito.']);
        return;
    }

    $input = reborn_json_input();
    $id = preg_replace('/[^a-zA-Z0-9_-]+/', '', (string) ($input['model_id'] ?? $input['id'] ?? '')) ?? '';
    if ($id === '' || !reborn_model_exists_in_library($id)) {
        reborn_json_response(404, ['message' => 'Modello non trovato.']);
        return;
    }
    if (($input['downloaded_in_page'] ?? false) !== true) {
        reborn_json_response(409, ['message' => 'Scarica il modello prima di assegnare una preferenza.']);
        return;
    }

    $stats = reborn_increment_model_library_stat($id, 'preference_count');
    reborn_json_response(200, [
        'status' => 'preference_recorded',
        'model_id' => $id,
        'download_count' => (int) ($stats['download_count'] ?? 0),
        'preference_count' => (int) ($stats['preference_count'] ?? 0),
    ]);
}

function reborn_handle_model_library_file_download(string $id): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        reborn_json_response(405, ['message' => 'Metodo non consentito.']);
        return;
    }
    $safeId = preg_replace('/[^a-zA-Z0-9_-]+/', '', $id) ?? '';
    if ($safeId === '') {
        reborn_json_response(404, ['message' => 'File non trovato.']);
        return;
    }
    foreach (reborn_read_library_records() as $record) {
        if ((string) ($record['id'] ?? '') !== $safeId) {
            continue;
        }
        $file = is_array($record['file'] ?? null) ? $record['file'] : [];
        $storagePath = (string) ($file['storage_path'] ?? '');
        if ($storagePath === '') {
            reborn_json_response(404, ['message' => 'File modello non disponibile.']);
            return;
        }
        $root = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
        $absolute = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storagePath));
        if ($absolute === false || strncmp($absolute, $root, strlen($root)) !== 0 || !is_file($absolute)) {
            reborn_json_response(404, ['message' => 'File modello non disponibile.']);
            return;
        }
        $previewOnly = in_array(strtolower(trim((string) ($_GET['preview'] ?? ''))), ['1', 'true', 'yes'], true);
        if (!$previewOnly) {
            $downloadSession = trim((string) ($_GET['download_session'] ?? $_GET['dl_session'] ?? ''));
            reborn_increment_model_library_download_once($safeId, $downloadSession);
        }
        $name = (string) ($file['original_name'] ?? basename($absolute));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'stl' => 'model/stl',
            'obj' => 'text/plain; charset=utf-8',
            '3mf' => 'model/3mf',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . (string) filesize($absolute));
        header('Content-Disposition: ' . ($previewOnly ? 'inline' : 'attachment') . '; filename="' . addcslashes(basename($name), '"\\') . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($absolute);
        return;
    }
    reborn_json_response(404, ['message' => 'File non trovato.']);
}

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $staticFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($requestPath));

    if ($requestPath !== '/' && is_file($staticFile)) {
        return false;
    }
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';


if ($path === '/api/v1/model-library/contributions') {
    reborn_handle_model_library_contribution();
    return;
}


if ($path === '/api/v1/model-library/preferences') {
    reborn_handle_model_library_preference();
    return;
}

if ($path === '/api/v1/model-library/matches') {
    reborn_handle_model_library_matches();
    return;
}

if (preg_match('#^/api/v1/model-library/files/([a-zA-Z0-9_-]+)$#', $path, $matches)) {
    reborn_handle_model_library_file_download($matches[1]);
    return;
}

if ($path === '/' || $path === '') {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $prototype = __DIR__ . '/prototype/index.html';
    if (is_file($prototype)) {
        $html = (string) file_get_contents($prototype);
        $html = str_replace('href="assets/', 'href="/prototype/assets/', $html);
        $html = str_replace('src="assets/', 'src="/prototype/assets/', $html);
        echo $html;
        return;
    }

    http_response_code(503);
    echo 'Re-born UI non disponibile.';
    return;
}

if ($path === '/api' || $path === '/api/') {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    echo '<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Re-born API</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f7f5ef; color: #171717; margin: 0; padding: 48px; line-height: 1.5; }
    .card { max-width: 760px; border: 1px solid #d8d3c8; background: white; padding: 32px; border-radius: 16px; }
    code { background: #f1eee7; padding: 2px 6px; border-radius: 4px; }
    a { color: #0657ff; }
  </style>
</head>
<body>
  <main class="card">
    <h1>Re-born API</h1>
    <p>Interfaccia prodotto: <a href="/">/</a></p>
    <p>Health check: <a href="/api/health">/api/health</a></p>
    <p>Setup locale: <code>php scripts/setup-dev.php</code></p>
  </main>
</body>
</html>';

    return;
}

$app = require dirname(__DIR__) . '/bootstrap/app.php';

Reborn\Shared\Http\SecurityHeaders::apply($app['config']['security'] ?? []);

/** @var Reborn\Shared\Http\Router $router */
$router = $app['router'];
$router->dispatch()->send();
