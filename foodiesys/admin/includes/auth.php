<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('foodiesys_admin');
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
