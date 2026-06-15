<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getPdo(): PDO
{
    $configFiles = [
        __DIR__ . '/db.php',
        __DIR__ . '/config.php',
        dirname(__DIR__) . '/db.php',
        dirname(__DIR__) . '/config.php',
    ];

    foreach ($configFiles as $file) {
        if (!is_file($file)) {
            continue;
        }

        require_once $file;

        if (isset($pdo) && $pdo instanceof PDO) {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        }

        if (function_exists('getDbConnection')) {
            $connection = getDbConnection();
            if ($connection instanceof PDO) {
                $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                return $connection;
            }
        }
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $database = getenv('DB_NAME') ?: 'calltrack';
    $user = getenv('DB_USER') ?: 'calltrack';
    $password = getenv('DB_PASS') ?: '';
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
    $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function readRequestPayload(): array
{
    $rawBody = file_get_contents('php://input') ?: '';
    $json = json_decode($rawBody, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        return $json;
    }

    return $_POST ?: [];
}

function normalizeIds($value): array
{
    if ($value === null || $value === '') {
        return [];
    }

    if (!is_array($value)) {
        $value = explode(',', (string) $value);
    }

    $ids = [];
    foreach ($value as $id) {
        $id = trim((string) $id);
        if ($id !== '' && ctype_digit($id)) {
            $ids[] = (int) $id;
        }
    }

    return array_values(array_unique($ids));
}

function deleteCalls(PDO $pdo, array $payload): void
{
    $ids = normalizeIds($payload['ids'] ?? $payload['id'] ?? null);

    if (!$ids) {
        jsonResponse(['success' => false, 'error' => 'IDS_REQUIRED'], 400);
    }

    $placeholders = [];
    $params = [];
    foreach ($ids as $index => $id) {
        $key = ':id' . $index;
        $placeholders[] = $key;
        $params[$key] = $id;
    }

    $sql = 'DELETE FROM calls WHERE id IN (' . implode(',', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $id) {
        $stmt->bindValue($key, $id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $deleted = $stmt->rowCount();

    if ($deleted < 1) {
        jsonResponse(['success' => false, 'error' => 'NO_ROWS_DELETED'], 404);
    }

    jsonResponse(['success' => true, 'deleted' => $deleted]);
}

function loadCalls(PDO $pdo): void
{
    $where = [];
    $params = [];

    $filters = [
        'manager' => 'manager',
        'phone' => 'phone',
        'user_phone' => 'user_phone',
    ];

    foreach ($filters as $param => $column) {
        $value = trim((string) ($_GET[$param] ?? ''));
        if ($value === '') {
            continue;
        }

        $where[] = "{$column} = :{$param}";
        $params[':' . $param] = $value;
    }

    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $where[] = 'call_date >= :date_from';
        $params[':date_from'] = $dateFrom;
    }

    $dateTo = trim((string) ($_GET['date_to'] ?? ''));
    if ($dateTo !== '') {
        $where[] = 'call_date <= :date_to';
        $params[':date_to'] = $dateTo;
    }

    $sql = 'SELECT
        id,
        call_date,
        call_time,
        phone,
        call_type,
        duration,
        manager,
        comment,
        tag,
        reminder,
        reminder_text,
        client,
        call_id,
        user_phone,
        created_at,
        updated_at
    FROM calls';

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY call_date DESC, call_time DESC, id DESC';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();

    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = getPdo();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST') {
        $payload = readRequestPayload();
        $action = strtolower(trim((string) ($payload['action'] ?? $_GET['action'] ?? '')));

        if ($action === 'delete') {
            deleteCalls($pdo, $payload);
        }

        jsonResponse(['success' => false, 'error' => 'UNSUPPORTED_ACTION'], 400);
    }

    if ($method === 'GET') {
        loadCalls($pdo);
    }

    jsonResponse(['success' => false, 'error' => 'METHOD_NOT_ALLOWED'], 405);
} catch (Throwable $error) {
    error_log('get_calls.php error: ' . $error->getMessage());
    jsonResponse(['success' => false, 'error' => $error->getMessage()], 500);
}
