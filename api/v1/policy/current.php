<?php
// The rental policy the customer must accept to submit a booking.
require_once __DIR__ . '/../common/bootstrap.php';
require_once __DIR__ . '/../../../lib/requirements.php';

$policy = requirements_current_policy($pdo);
if (!$policy) {
  api_error('No rental policy is currently published.', [], 404);
}

api_success([
  'version' => $policy['version'],
  'effective_from' => $policy['effective_from'],
  'content_html' => $policy['content_html'],
]);
