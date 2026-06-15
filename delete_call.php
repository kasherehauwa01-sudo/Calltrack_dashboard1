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

function normalizeId($value): ?int
{
    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (is_string($value) && ctype_digit($value)) {
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    return null;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'METHOD_NOT_ALLOWED'], 405);
    }

    $payload = readJsonPayload();
    $id = normalizeId($payload['id'] ?? null);

    if ($id === null) {
        jsonResponse(['success' => false, 'error' => 'ID_REQUIRED'], 400);
    }

    $pdo = createPdoConnection();
    $stmt = $pdo->prepare('DELETE FROM calls WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() < 1) {
        jsonResponse(['success' => false, 'error' => 'NO_ROWS_DELETED'], 404);
    }

    jsonResponse(['success' => true]);
} catch (Throwable $error) {
    error_log('delete_call.php error: ' . $error->getMessage());
    jsonResponse(['success' => false, 'error' => $error->getMessage()], 500);
}
