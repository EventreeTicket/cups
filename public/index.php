<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    $databaseDirectory = dirname(__DIR__) . '/data';
    if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0775, true) && !is_dir($databaseDirectory)) {
        throw new RuntimeException('Kan de datamap niet aanmaken.');
    }

    $db = new PDO('sqlite:' . $databaseDirectory . '/rfid.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA journal_mode = WAL');
    initialiseDatabase($db);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
    $path = $path === '' ? '/' : $path;

    if ($method === 'GET' && $path === '/health') {
        respond(['status' => 'ok']);
    }

    if ($method === 'POST' && preg_match('#^/api/scans/(in|out)$#', $path, $matches)) {
        createScanBatch($db, strtoupper($matches[1]));
    }

    if ($method === 'POST' && $path === '/api/demo/reset') {
        resetDemoData($db);
    }

    if ($method === 'GET' && $path === '/api/cups') {
        listCups($db);
    }

    if ($method === 'GET' && preg_match('#^/api/cups/(.+)$#', $path, $matches)) {
        showCup($db, urldecode($matches[1]));
    }

    respond(['error' => 'Route niet gevonden'], 404);
} catch (InvalidArgumentException $exception) {
    respond(['error' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    respond(['error' => 'Interne serverfout'], 500);
}

function initialiseDatabase(PDO $db): void
{
    $db->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS scan_batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            request_id TEXT UNIQUE,
            direction TEXT NOT NULL CHECK(direction IN ('IN', 'OUT')),
            source TEXT,
            tag_count INTEGER NOT NULL,
            received_at TEXT NOT NULL,
            response_json TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS cup_status (
            tag TEXT PRIMARY KEY,
            status TEXT NOT NULL CHECK(status IN ('IN', 'OUT')),
            last_scanned_at TEXT NOT NULL,
            last_source TEXT,
            updated_by_batch_id INTEGER NOT NULL,
            FOREIGN KEY(updated_by_batch_id) REFERENCES scan_batches(id)
        );

        CREATE TABLE IF NOT EXISTS scan_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            batch_id INTEGER NOT NULL,
            tag TEXT NOT NULL,
            direction TEXT NOT NULL CHECK(direction IN ('IN', 'OUT')),
            source TEXT,
            scanned_at TEXT NOT NULL,
            FOREIGN KEY(batch_id) REFERENCES scan_batches(id)
        );

        CREATE INDEX IF NOT EXISTS scan_events_tag_scanned_at ON scan_events(tag, scanned_at DESC);
    SQL);
}

function createScanBatch(PDO $db, string $direction): never
{
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Verstuur een geldig JSON-object.');
    }

    $tags = $payload['tags'] ?? null;
    if (!is_array($tags) || $tags === []) {
        throw new InvalidArgumentException('tags moet een niet-lege lijst zijn.');
    }

    $tags = array_values(array_unique(array_map(static function (mixed $tag): string {
        if (!is_string($tag) || trim($tag) === '') {
            throw new InvalidArgumentException('Iedere tag moet een niet-lege tekst zijn.');
        }
        return trim($tag);
    }, $tags)));

    $requestId = optionalText($payload['request_id'] ?? null, 'request_id');
    $source = optionalText($payload['source'] ?? null, 'source');

    if ($requestId !== null) {
        $existing = $db->prepare('SELECT response_json FROM scan_batches WHERE request_id = :request_id');
        $existing->execute(['request_id' => $requestId]);
        $oldResponse = $existing->fetchColumn();
        if ($oldResponse !== false) {
            respond(json_decode((string) $oldResponse, true));
        }
    }

    $scannedAt = gmdate('c');
    $db->beginTransaction();
    try {
        $batch = $db->prepare(
            'INSERT INTO scan_batches (request_id, direction, source, tag_count, received_at, response_json)
             VALUES (:request_id, :direction, :source, :tag_count, :received_at, :response_json)'
        );
        // Wordt na het verwerken vervangen door het definitieve antwoord.
        $batch->execute([
            'request_id' => $requestId,
            'direction' => $direction,
            'source' => $source,
            'tag_count' => count($tags),
            'received_at' => $scannedAt,
            'response_json' => '{}',
        ]);
        $batchId = (int) $db->lastInsertId();

        $event = $db->prepare(
            'INSERT INTO scan_events (batch_id, tag, direction, source, scanned_at)
             VALUES (:batch_id, :tag, :direction, :source, :scanned_at)'
        );
        $status = $db->prepare(
            // INSERT OR REPLACE werkt ook op de oudere SQLite-versie van Plesk.
            'INSERT OR REPLACE INTO cup_status (tag, status, last_scanned_at, last_source, updated_by_batch_id)
             VALUES (:tag, :status, :last_scanned_at, :last_source, :batch_id)'
        );

        foreach ($tags as $tag) {
            $event->execute([
                'batch_id' => $batchId,
                'tag' => $tag,
                'direction' => $direction,
                'source' => $source,
                'scanned_at' => $scannedAt,
            ]);
            $status->execute([
                'tag' => $tag,
                'status' => $direction,
                'last_scanned_at' => $scannedAt,
                'last_source' => $source,
                'batch_id' => $batchId,
            ]);
        }

        $response = [
            'batch_id' => $batchId,
            'direction' => strtolower($direction),
            'scanned_at' => $scannedAt,
            'processed_tags' => count($tags),
            'tags' => $tags,
        ];
        $saveResponse = $db->prepare('UPDATE scan_batches SET response_json = :response WHERE id = :id');
        $saveResponse->execute(['response' => json_encode($response, JSON_THROW_ON_ERROR), 'id' => $batchId]);
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    respond($response, 201);
}

function listCups(PDO $db): never
{
    $rows = $db->query('SELECT tag, status, last_scanned_at, last_source FROM cup_status ORDER BY last_scanned_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    respond(['count' => count($rows), 'cups' => $rows]);
}

/** Leegt uitsluitend de demo-registraties; de SQLite-database blijft bestaan. */
function resetDemoData(PDO $db): never
{
    $db->beginTransaction();
    try {
        $events = $db->exec('DELETE FROM scan_events');
        $cups = $db->exec('DELETE FROM cup_status');
        $batches = $db->exec('DELETE FROM scan_batches');
        $sequence = $db->prepare("DELETE FROM sqlite_sequence WHERE name IN ('scan_events', 'cup_status', 'scan_batches')");
        $sequence->execute();
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }

    respond([
        'deleted' => true,
        'deleted_events' => $events,
        'deleted_cups' => $cups,
        'deleted_batches' => $batches,
    ]);
}

function showCup(PDO $db, string $tag): never
{
    $cup = $db->prepare('SELECT tag, status, last_scanned_at, last_source FROM cup_status WHERE tag = :tag');
    $cup->execute(['tag' => $tag]);
    $current = $cup->fetch(PDO::FETCH_ASSOC);
    if ($current === false) {
        respond(['error' => 'Tag niet gevonden'], 404);
    }

    $events = $db->prepare('SELECT direction, source, scanned_at, batch_id FROM scan_events WHERE tag = :tag ORDER BY id DESC');
    $events->execute(['tag' => $tag]);
    respond(['cup' => $current, 'events' => $events->fetchAll(PDO::FETCH_ASSOC)]);
}

function optionalText(mixed $value, string $field): ?string
{
    if ($value === null) {
        return null;
    }
    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException("$field moet tekst zijn.");
    }
    return trim($value);
}

function respond(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
