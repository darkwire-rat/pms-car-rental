<?php
// Renders the current rental policy in a scrollable panel.
require_once __DIR__ . '/../lib/requirements.php';
$policyRow = requirements_current_policy($pdo);
$policyShowCheckbox = $policyShowCheckbox ?? false;
?>
<?php if ($policyRow): ?>
  <div class="border rounded p-3 bg-body-tertiary mb-2" style="max-height: 220px; overflow-y: auto;" tabindex="0"
       aria-label="Rental policy version <?= htmlspecialchars($policyRow['version']) ?>">
    <?= $policyRow['content_html'] /* trusted admin content from rental_policies */ ?>
  </div>
  <?php if ($policyShowCheckbox): ?>
    <input type="hidden" id="policy_version" value="<?= htmlspecialchars($policyRow['version']) ?>">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="policy_accepted">
      <label class="form-check-label" for="policy_accepted">
        I have read and accept the Rental Policy (v<?= htmlspecialchars($policyRow['version']) ?>).
      </label>
    </div>
  <?php endif; ?>
<?php else: ?>
  <p class="text-body-secondary small mb-0">No rental policy is published.</p>
<?php endif; ?>
