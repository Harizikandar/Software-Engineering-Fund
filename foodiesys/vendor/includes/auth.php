<?php
require_once __DIR__ . '/session.php';

if (empty($_SESSION['vendor_id'])) {
    header('Location: login.php');
    exit;
}
