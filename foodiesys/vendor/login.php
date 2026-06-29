<?php
require_once __DIR__ . '/includes/session.php';

if (!empty($_SESSION['vendor_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT vendor_id, stall_name, password, approval_status FROM vendors WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if ($vendor && password_verify($password, $vendor['password'])) {
        if ($vendor['approval_status'] !== 'Approved') {
            $error = 'Your stall registration is still ' . strtolower($vendor['approval_status']) . '. Please wait for admin approval before logging in.';
        } else {
            session_regenerate_id(true);
            $_SESSION['vendor_id'] = $vendor['vendor_id'];
            $_SESSION['stall_name'] = $vendor['stall_name'];
            header('Location: dashboard.php');
            exit;
        }
    } else {
        $error = 'Invalid vendor credentials. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Login - Food Campus System</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #0a47a8; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
  .logo-container { text-align: center; margin-bottom: 12px; }
  .logo-container img { width: 120px; }
  .login-card { background-color: #d9d9d9; border-radius: 4px; padding: 36px 32px; width: 100%; max-width: 380px; }
  .login-card h1 { font-size: 24px; color: #0a47a8; margin-bottom: 6px; }
  .login-card p { font-size: 13px; color: #555; margin-bottom: 22px; }
  .message { padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 14px; background-color: #f8d7da; color: #a71d2a; }
  .form-row { margin-bottom: 16px; }
  .form-row label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 5px; color: #1a1a1a; }
  .form-row input { width: 100%; padding: 10px 12px; border: 1px solid #aaa; border-radius: 4px; font-size: 14px; font-family: inherit; background-color: #fff; }
  .btn { width: 100%; padding: 12px 0; background-color: #0a47a8; color: #fff; border: none; border-radius: 4px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 8px; }
  .btn:hover { background-color: #08368a; }
  .register-link { display: block; text-align: center; margin-top: 18px; font-size: 13px; color: #0a47a8; text-decoration: none; }
  .register-link:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="logo-container">
  <img src="assets/images/logo.png" alt="FoodieSys">
</div>
<div class="login-card">
  <h1>Vendor Login</h1>
  <p>Sign in to manage your stall, menu, and incoming orders.</p>
  <?php if ($error !== ''): ?>
    <div class="message"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <form method="POST" action="login.php">
    <div class="form-row">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
    </div>
    <div class="form-row">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn">Login</button>
  </form>
  <a href="register.php" class="register-link">Register a new stall</a>
</div>
</body>
</html>
