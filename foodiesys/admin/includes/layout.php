<?php
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function status_badge(string $status): string {
    $map = [
        'Paid' => 'success', 'Approved' => 'success', 'Visible' => 'success', 'Open' => 'success',
        'Pending' => 'warning', 'Pending Payment' => 'warning', 'Closed' => 'secondary',
        'Failed' => 'danger', 'Rejected' => 'danger', 'Suspended' => 'danger', 'Hidden' => 'dark',
        'Refunded' => 'info', 'Preparing' => 'primary',
        'Ready for Pickup' => 'primary', 'Completed' => 'success', 'Cancelled' => 'danger',
    ];
    return '<span class="badge text-bg-' . ($map[$status] ?? 'secondary') . '">' . e($status) . '</span>';
}

function admin_nav_links(string $active): void { ?>
    <a class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
    <a class="nav-link <?= $active === 'users' ? 'active' : '' ?>" href="users.php">Users &amp; Vendors</a>
    <a class="nav-link <?= $active === 'payments' ? 'active' : '' ?>" href="payments.php">Payment Monitoring</a>
    <a class="nav-link <?= $active === 'reviews' ? 'active' : '' ?>" href="reviews.php">Review Moderation</a>
    <a class="nav-link <?= $active === 'analytics' ? 'active' : '' ?>" href="analytics.php">Analytics &amp; Reports</a>
<?php }

function admin_header(string $title, string $active = ''): void {
    global $active_admin_page;
    $active_admin_page = $active;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | FoodieSys Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <a class="brand brand-desktop" href="dashboard.php" aria-label="FoodieSys Admin dashboard">
            <img src="assets/images/foodiesys-logo.png" alt="FoodieSys">
            <small>ADMIN PORTAL</small>
        </a>
        <a class="brand brand-compact" href="dashboard.php" aria-label="FoodieSys Admin dashboard">
            <img src="assets/images/foodiesys-crest.png" alt="FoodieSys">
        </a>
        <nav class="nav flex-column desktop-nav">
            <?php admin_nav_links($active); ?>
        </nav>
        <div class="sidebar-bottom">
            <div class="admin-name">Signed in as<br><strong><?= e($_SESSION['admin_name'] ?? 'Administrator') ?></strong></div>
            <a class="logout-link" href="logout.php">Log out</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-brand-mobile">
                <img src="assets/images/foodiesys-crest.png" alt="FoodieSys">
            </div>
            <div class="topbar-title">
                <h1><?= e($title) ?></h1>
                <p>Campus Food Ordering and Management System</p>
            </div>
            <div class="topbar-actions">
                <div class="topbar-avatar d-none d-lg-grid"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
                <button class="menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMenu" aria-controls="adminMenu" aria-label="Open navigation menu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </header>
<?php }

function admin_footer(): void { ?>
    </main>
</div>
<div class="offcanvas offcanvas-end admin-offcanvas" tabindex="-1" id="adminMenu" aria-labelledby="adminMenuLabel">
    <div class="offcanvas-header">
        <div class="offcanvas-logo">
            <img src="assets/images/foodiesys-logo.png" alt="FoodieSys">
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <nav class="nav flex-column offcanvas-nav">
            <?php global $active_admin_page; admin_nav_links($active_admin_page ?? ''); ?>
        </nav>
        <div class="offcanvas-account mt-auto">
            <div>Signed in as <strong><?= e($_SESSION['admin_name'] ?? 'Administrator') ?></strong></div>
            <a href="logout.php">Log out</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php }
