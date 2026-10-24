<?php
// Dedicated admin logout.
session_start();
require 'includes/admin_gate.php'; // for ADMIN_ACCESS_KEYWORD
session_unset();
session_destroy();
header('Location: admin-login.php?key=' . urlencode(ADMIN_ACCESS_KEYWORD));
exit;
