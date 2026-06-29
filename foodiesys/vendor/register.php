<?php
require_once __DIR__ . '/includes/session.php';

if (!empty($_SESSION['vendor_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stall_name      = trim($_POST['stall_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $operating_hours = trim($_POST['operating_hours'] ?? '');

    if ($stall_name === '' || $email === '' || $password === '' || $operating_hours === '') {
        $error = 'All fields are required.';
    } else {
        $check = $conn->prepare('SELECT vendor_id FROM vendors WHERE email = ?');
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'An account with this email already exists.';
        }
        $check->close();

        if ($error === '') {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                "INSERT INTO vendors (stall_name, email, password, operating_hours, approval_status, stall_status)
                 VALUES (?, ?, ?, ?, 'Pending', 'Closed')"
            );
            $stmt->bind_param('ssss', $stall_name, $email, $hashed, $operating_hours);
            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = 'Registration failed: ' . $conn->error;
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register New Stall - Food Campus System</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #0a47a8; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; }
  .logo-container { text-align: center; margin-bottom: 12px; }
  .logo-container img { width: 120px; }
  .login-card { background-color: #d9d9d9; border-radius: 4px; padding: 36px 32px; width: 100%; max-width: 420px; }
  .login-card h1 { font-size: 24px; color: #0a47a8; margin-bottom: 6px; }
  .login-card p { font-size: 13px; color: #555; margin-bottom: 22px; }
  .message { padding: 10px 14px; border-radius: 4px; margin-bottom: 18px; font-size: 14px; }
  .message.error { background-color: #f8d7da; color: #a71d2a; }
  .message.success { background-color: #d4edda; color: #1e7e34; }
  .form-row { margin-bottom: 16px; }
  .form-row label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 5px; color: #1a1a1a; }
  .form-row input { width: 100%; padding: 10px 12px; border: 1px solid #aaa; border-radius: 4px; font-size: 14px; font-family: inherit; background-color: #fff; }
  .btn { width: 100%; padding: 12px 0; background-color: #0a47a8; color: #fff; border: none; border-radius: 4px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 8px; }
  .btn:hover { background-color: #08368a; }
  .back-link { display: block; text-align: center; margin-top: 18px; font-size: 13px; color: #0a47a8; text-decoration: none; }
  .back-link:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="logo-container">
  <img src="assets/images/logo.png" alt="FoodieSys">
</div>
<div class="login-card">
  <h1>Register New Stall</h1>
  <p>Your account will be reviewed by an admin before you can log in.</p>

  <?php if ($success): ?>
    <div class="message success">Registration submitted! Your stall is pending admin approval. You'll be able to log in once approved.</div>
    <a href="login.php" class="back-link">Back to Login</a>
  <?php else: ?>
    <?php if ($error !== ''): ?>
      <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" action="register.php">
      <div class="form-row">
        <label for="stall_name">Stall Name</label>
        <input type="text" id="stall_name" name="stall_name" required value="<?php echo htmlspecialchars($_POST['stall_name'] ?? ''); ?>">
      </div>
      <div class="form-row">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>
      <div class="form-row">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <div class="form-row">
        <label for="operating_hours">Operating Hours</label>
        <input type="text" id="operating_hours" name="operating_hours" placeholder="e.g. 9:00 AM - 5:00 PM" required value="<?php echo htmlspecialchars($_POST['operating_hours'] ?? ''); ?>">
      </div>
      <button type="submit" class="btn">Register</button>
    </form>
    <a href="login.php" class="back-link">Back to Login</a>
  <?php endif; ?>
</div>
</body>
</html>
