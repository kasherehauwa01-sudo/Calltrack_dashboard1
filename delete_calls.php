<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function createPdoConnection(): PDO
{
    require_once __DIR__ . '/db.php';

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

    if (function_exists('getPdo')) {
        $connection = getPdo();
        if ($connection instanceof PDO) {
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $connection;
        }
    }

    throw new RuntimeException('PDO_CONNECTION_NOT_FOUND');
}

function readJsonPayload(): array
{
    $rawBody = file_get_contents('php://input') ?: '';
    $payload = json_decode($rawBody, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
        jsonResponse(['success' => false, 'error' => 'INVALID_JSON'], 400);
    }

    return $payload;
}

function normalizeIds($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $ids = [];
    foreach ($value as $id) {
        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
    }

    return array_values(array_unique($ids));
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'METHOD_NOT_ALLOWED'], 405);
    }

    $payload = readJsonPayload();
    $ids = normalizeIds($payload['ids'] ?? null);

    if (!$ids) {
        jsonResponse(['success' => false, 'error' => 'IDS_REQUIRED'], 400);
    }

    $placeholders = [];
    $params = [];
    foreach ($ids as $index => $id) {
        $placeholder = ':id' . $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $id;
    }

    $pdo = createPdoConnection();
    $stmt = $pdo->prepare('DELETE FROM calls WHERE id IN (' . implode(',', $placeholders) . ')');

    foreach ($params as $placeholder => $id) {
        $stmt->bindValue($placeholder, $id, PDO::PARAM_INT);
    }

    $stmt->execute();
    $deleted = $stmt->rowCount();

    if ($deleted < 1) {
        jsonResponse(['success' => false, 'error' => 'NO_ROWS_DELETED'], 404);
    }

    jsonResponse(['success' => true, 'deleted' => $deleted]);
} catch (Throwable $error) {
    error_log('delete_calls.php error: ' . $error->getMessage());
    jsonResponse(['success' => false, 'error' => $error->getMessage()], 500);
}
