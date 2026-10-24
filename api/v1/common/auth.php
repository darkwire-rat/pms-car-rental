<?php
// Bearer-token auth against the api_tokens table.

define('API_TOKEN_TTL_DAYS', 30);

function api_issue_token(PDO $pdo, int $userId): string {
  $raw = bin2hex(random_bytes(32));
  $hash = hash('sha256', $raw);
  $expires = date('Y-m-d H:i:s', strtotime('+' . API_TOKEN_TTL_DAYS . ' days'));
  $stmt = $pdo->prepare("INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)");
  $stmt->execute([$userId, $hash, $expires]);
  return $raw;
}

function api_revoke_token(PDO $pdo, string $rawToken): void {
  $hash = hash('sha256', $rawToken);
  $stmt = $pdo->prepare("DELETE FROM api_tokens WHERE token_hash = ?");
  $stmt->execute([$hash]);
}

// Reads the raw Authorization header.
function api_authorization_header(): string {
  foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
    if (!empty($_SERVER[$key])) {
      return (string) $_SERVER[$key];
    }
  }
  $headers = function_exists('apache_request_headers') ? apache_request_headers()
    : (function_exists('getallheaders') ? getallheaders() : []);
  foreach ((array) $headers as $name => $value) {
    if (strcasecmp($name, 'Authorization') === 0 && $value !== '') {
      return (string) $value;
    }
  }
  return '';
}

function api_bearer_token(): ?string {
  $header = api_authorization_header();
  if (stripos($header, 'Bearer ') === 0) {
    return trim(substr($header, 7));
  }
  // Fallback for endpoints opened directly as a URL, which can't attach an Authorization header.
  if (!empty($_GET['token'])) {
    return trim($_GET['token']);
  }
  return null;
}

// Returns the authenticated user row (id, name, email) or null if the token is missing/invalid/expired.
function api_current_user(PDO $pdo): ?array {
  $raw = api_bearer_token();
  if (!$raw) {
    return null;
  }
  $hash = hash('sha256', $raw);
  $stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email FROM api_tokens t
     JOIN users u ON u.id = t.user_id
     WHERE t.token_hash = ? AND t.expires_at > NOW()"
  );
  $stmt->execute([$hash]);
  $user = $stmt->fetch();
  return $user ?: null;
}

// Ends the request with 401 if there is no valid token; otherwise returns the authenticated user.
function api_require_auth(PDO $pdo): array {
  $user = api_current_user($pdo);
  if (!$user) {
    api_error('Missing or invalid authentication token.', [], 401);
  }
  return $user;
}
