<?php
// Consistent JSON response envelope for all /api/v1 endpoints.

function api_success($data = [], $message = '', $code = 200) {
  http_response_code($code);
  echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
  exit;
}

function api_error($message, $errors = [], $code = 400) {
  http_response_code($code);
  $body = ['success' => false, 'message' => $message];
  if ($errors) {
    $body['errors'] = $errors;
  }
  echo json_encode($body);
  exit;
}
