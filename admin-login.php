<?php
session_start();
include 'db_connect.php';
require 'includes/admin_gate.php';
require_admin_keyword(); // no keyword in the URL -> bounced to index.php


$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);

  // Check credentials in the database
  $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 1) {
    $admin = $result->fetch_assoc();
    // Verify password (if hashed)
    if (password_verify($password, $admin['password'])) {
      $_SESSION['admin_id'] = $admin['id'];
      $_SESSION['admin_name'] = $admin['name'];
      header('Location: admin-dashboard.php');
      exit;
    } else {
      $error = 'Invalid password.';
    }
  } else {
    $error = 'Admin not found.';
  }
}

?>
<!doctype html>
<html lang="en">
<head>
  <script>
    // No-flash theme bootstrap.
    (function () {
      var stored = localStorage.getItem('pms-theme');
      var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="utf-8">
  <title>PMS Car Rental — Admin Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
  <main class="d-flex flex-column align-items-center justify-content-center vh-100">
    <div class="mb-3">
      <img src="assets/new-logo-bg-remove.png" alt="PMS Logo" style="width:56px;height:44px;border-radius:8px;">
    </div>
    <div class="bg-body rounded-4 shadow p-4" style="min-width:320px;max-width:380px;">
      <h1 class="h3 mb-3 text-center font-poppins">Admin Dashboard Access</h1>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?php echo $error; ?></div>
      <?php endif; ?>
      <form method="POST" autocomplete="off" aria-label="Admin Login">
        <input type="hidden" name="key" value="<?php echo htmlspecialchars(ADMIN_ACCESS_KEYWORD); ?>"><!-- keeps the keyword gate satisfied on submit -->
        <div class="mb-3">
          <label for="adminEmail" class="form-label">Email</label>
          <input type="email" class="form-control" id="adminEmail" name="email" required>
        </div>
        <div class="mb-3">
          <label for="adminPassword" class="form-label">Password</label>
          <input type="password" class="form-control" id="adminPassword" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 rounded-pill mb-2">Login</button>
        <a href="index.php" class="btn btn-link w-100">Back to Home</a>
      </form>
    </div>
  </main>
</body>
</html>
