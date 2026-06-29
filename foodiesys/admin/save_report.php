<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: analytics.php'); exit; }
$from = $_POST['date_from'] ?? ''; $to = $_POST['date_to'] ?? '';
$type = 'System Sales Report' . ($from && $to ? " ($from to $to)" : '');
$stmt = $pdo->prepare('INSERT INTO reports (report_type,total_sales,best_vendor_id,lowest_vendor_id,admin_id) VALUES (?,?,?,?,?)');
$stmt->execute([$type, (float)($_POST['total_sales'] ?? 0), $_POST['best_vendor_id'] !== '' ? (int)$_POST['best_vendor_id'] : null, $_POST['lowest_vendor_id'] !== '' ? (int)$_POST['lowest_vendor_id'] : null, $_SESSION['admin_id']]);
header('Location: analytics.php?date_from='.urlencode($from).'&date_to='.urlencode($to).'&saved=1');
exit;
