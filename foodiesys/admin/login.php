<?php
session_name('foodiesys_admin');
session_start();
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}
require_once __DIR__ . '/../config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT admin_id, admin_name, email, password, role FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    // Supports password_hash() values. Plain-text fallback is only for the supplied coursework database if it contains demo passwords.
    $valid = $admin && (password_verify($password, $admin['password']) || hash_equals($admin['password'], $password));

    if ($valid) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['admin_name'];
        $_SESSION['admin_role'] = $admin['role'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | FoodieSys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body class="login-page">
    <form class="login-card" method="post" novalidate>
        <div class="login-logo"><img src="assets/images/foodiesys-logo.png" alt="FoodieSys"></div>
        <div class="text-uppercase text-secondary fw-bold" style="font-size:11px;letter-spacing:1.5px">Campus Food System</div>
        <h1>Administrator Login</h1>
        <p>Sign in to manage campus users, payments, reviews, and reports.</p>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="mb-3"><label class="form-label fw-semibold">Email</label><input class="form-control" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="admin@example.com"></div>
        <div class="mb-4"><label class="form-label fw-semibold">Password</label><input class="form-control" type="password" name="password" required placeholder="Enter password"></div>
        <button class="btn btn-brand w-100 py-2 fw-bold" type="submit">Sign In</button>
    </form>
</body>
</html>
