<?php
/* There was no CSRF protection anywhere before this. */

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): bool
{
    if ($token === null || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/* Reads the token from the X-CSRF-Token header, a `csrf_token` POST field, or a `csrf_token` key in a JSON body. */
function csrf_require(): void
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $token = $header !== '' ? $header : ($_POST['csrf_token'] ?? '');

    if ($token === '') {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $body = json_decode(file_get_contents('php://input'), true);
            if (is_array($body) && isset($body['csrf_token'])) {
                $token = (string) $body['csrf_token'];
            }
        }
    }

    if (!csrf_validate($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid or missing CSRF token. Reload the page and try again.']);
        exit;
    }
}
