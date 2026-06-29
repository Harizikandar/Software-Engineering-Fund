<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/layout.php';

function scalar(PDO $pdo, string $sql): int|float { return $pdo->query($sql)->fetchColumn() ?: 0; }
$stats = [
    'customers' => scalar($pdo, 'SELECT COUNT(*) FROM customers'),
    'vendors' => scalar($pdo, 'SELECT COUNT(*) FROM vendors'),
    'pending_vendors' => scalar($pdo, "SELECT COUNT(*) FROM vendors WHERE approval_status = 'Pending'"),
    'paid_revenue' => scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status = 'Paid'"),
    'pending_payments' => scalar($pdo, "SELECT COUNT(*) FROM payments WHERE payment_status = 'Pending Payment'"),
    'pending_reviews' => scalar($pdo, "SELECT COUNT(*) FROM reviews WHERE review_status = 'Pending'"),
];
$recentPayments = $pdo->query("SELECT p.payment_id,p.amount,p.payment_method,p.payment_status,p.payment_date,c.name,v.stall_name FROM payments p JOIN customers c ON c.cust_id=p.cust_id JOIN vendors v ON v.vendor_id=p.vendor_id ORDER BY p.payment_date DESC LIMIT 6")->fetchAll();
$pendingVendors = $pdo->query("SELECT vendor_id,stall_name,email,operating_hours,approval_status FROM vendors WHERE approval_status='Pending' ORDER BY vendor_id DESC LIMIT 6")->fetchAll();
admin_header('Admin Dashboard', 'dashboard');
?>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><div class="stat-card"><div class="label">Total Customers</div><div class="value"><?= number_format($stats['customers']) ?></div><div class="note">Registered customer accounts</div></div></div>
    <div class="col-md-6 col-xl-3"><div class="stat-card"><div class="label">Total Vendors</div><div class="value"><?= number_format($stats['vendors']) ?></div><div class="note"><?= number_format($stats['pending_vendors']) ?> awaiting approval</div></div></div>
    <div class="col-md-6 col-xl-3"><div class="stat-card"><div class="label">Paid Revenue</div><div class="value">RM <?= number_format((float)$stats['paid_revenue'], 2) ?></div><div class="note">All successful payments</div></div></div>
    <div class="col-md-6 col-xl-3"><div class="stat-card"><div class="label">Needs Attention</div><div class="value"><?= number_format($stats['pending_vendors'] + $stats['pending_payments'] + $stats['pending_reviews']) ?></div><div class="note">Vendors, payments & reviews</div></div></div>
</div>
<div class="row g-4">
  <div class="col-xl-7"><section class="panel"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="panel-title mb-0">Recent Payment Transactions</h2><a href="payments.php" class="btn btn-sm btn-outline-primary">View all</a></div>
  <div class="table-responsive"><table class="table table-hover"><thead><tr><th>Payment</th><th>Customer</th><th>Vendor</th><th>Amount</th><th>Status</th></tr></thead><tbody>
  <?php if (!$recentPayments): ?><tr><td colspan="5" class="empty-state">No payment records available yet.</td></tr><?php endif; ?>
  <?php foreach ($recentPayments as $p): ?><tr><td>#<?= e($p['payment_id']) ?><small class="d-block text-secondary"><?= e(date('d M Y, h:i A', strtotime($p['payment_date']))) ?></small></td><td><?= e($p['name']) ?></td><td><?= e($p['stall_name']) ?></td><td>RM <?= number_format((float)$p['amount'],2) ?></td><td><?= status_badge($p['payment_status']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section></div>
  <div class="col-xl-5"><section class="panel"><div class="d-flex justify-content-between align-items-center mb-2"><h2 class="panel-title mb-0">Pending Vendor Approval</h2><a href="users.php?tab=vendors&approval=Pending" class="btn btn-sm btn-outline-primary">Manage</a></div>
  <div class="table-responsive"><table class="table table-hover"><thead><tr><th>Stall</th><th>Hours</th><th>Status</th></tr></thead><tbody>
  <?php if (!$pendingVendors): ?><tr><td colspan="3" class="empty-state">No vendors waiting for approval.</td></tr><?php endif; ?>
  <?php foreach ($pendingVendors as $v): ?><tr><td><strong><?= e($v['stall_name']) ?></strong><small class="d-block text-secondary"><?= e($v['email']) ?></small></td><td><?= e($v['operating_hours']) ?></td><td><?= status_badge($v['approval_status']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section></div>
</div>
<?php admin_footer(); ?>
