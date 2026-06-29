<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/layout.php';

$flash = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_customer') {
            $stmt = $pdo->prepare('UPDATE customers SET name=?, email=? WHERE cust_id=?');
            $stmt->execute([trim($_POST['name']), trim($_POST['email']), (int)$_POST['cust_id']]);
            $flash = 'Customer information updated successfully.';
        } elseif ($action === 'delete_customer') {
            $pdo->prepare('DELETE FROM customers WHERE cust_id=?')->execute([(int)$_POST['cust_id']]);
            $flash = 'Customer account deleted successfully.';
        } elseif ($action === 'update_vendor') {
            $stmt = $pdo->prepare('UPDATE vendors SET stall_name=?, email=?, operating_hours=?, approval_status=?, stall_status=? WHERE vendor_id=?');
            $stmt->execute([trim($_POST['stall_name']), trim($_POST['email']), trim($_POST['operating_hours']), $_POST['approval_status'], $_POST['stall_status'], (int)$_POST['vendor_id']]);
            $flash = 'Vendor details updated successfully.';
        } elseif ($action === 'delete_vendor') {
            $pdo->prepare('DELETE FROM vendors WHERE vendor_id=?')->execute([(int)$_POST['vendor_id']]);
            $flash = 'Vendor account deleted successfully.';
        }
    } catch (PDOException $e) {
        $error = 'Action could not be completed. This account may have existing orders or linked records, so the database prevents deletion.';
    }
}
$tab = ($_GET['tab'] ?? 'customers') === 'vendors' ? 'vendors' : 'customers';
$search = trim($_GET['search'] ?? '');
$approval = trim($_GET['approval'] ?? '');

if ($tab === 'customers') {
    $sql = 'SELECT cust_id,name,email FROM customers'; $params=[];
    if ($search !== '') { $sql .= ' WHERE name LIKE ? OR email LIKE ?'; $params=["%$search%","%$search%"]; }
    $sql .= ' ORDER BY cust_id DESC';
    $stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
} else {
    $sql='SELECT vendor_id,stall_name,email,operating_hours,approval_status,stall_status FROM vendors'; $where=[];$params=[];
    if ($search !== '') { $where[]='(stall_name LIKE ? OR email LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }
    if (in_array($approval,['Pending','Approved','Rejected','Suspended'],true)) { $where[]='approval_status=?'; $params[]=$approval; }
    if ($where) $sql.=' WHERE '.implode(' AND ',$where); $sql.=' ORDER BY vendor_id DESC';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
}
admin_header('Users & Vendors Management','users');
?>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div class="btn-group"><a class="btn <?= $tab==='customers'?'btn-brand':'btn-outline-primary' ?>" href="users.php?tab=customers">Customers</a><a class="btn <?= $tab==='vendors'?'btn-brand':'btn-outline-primary' ?>" href="users.php?tab=vendors">Vendors</a></div><div class="text-secondary small align-self-center">The current database has no customer account-status column, so customers can be edited or deleted only.</div></div>
<form class="filter-bar row g-2 align-items-end" method="get"><input type="hidden" name="tab" value="<?= e($tab) ?>"><div class="col-md-6"><label class="form-label mb-1">Search <?= $tab==='customers'?'customer':'vendor' ?></label><input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search by name, stall or email"></div><?php if($tab==='vendors'): ?><div class="col-md-3"><label class="form-label mb-1">Approval Status</label><select class="form-select" name="approval"><option value="">All statuses</option><?php foreach(['Pending','Approved','Rejected','Suspended'] as $s): ?><option <?= $approval===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div><?php endif; ?><div class="col-md-3"><button class="btn btn-brand w-100">Apply Filters</button></div></form>
<section class="panel"><h2 class="panel-title"><?= $tab==='customers'?'Customer Accounts':'Vendor Accounts' ?></h2><div class="table-responsive"><table class="table table-hover"><thead><?php if($tab==='customers'): ?><tr><th>ID</th><th>Name</th><th>Email</th><th class="text-end">Action</th></tr><?php else: ?><tr><th>ID</th><th>Stall</th><th>Email</th><th>Operating Hours</th><th>Approval</th><th>Stall Status</th><th class="text-end">Action</th></tr><?php endif; ?></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="7" class="empty-state">No matching accounts found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?>
<?php if($tab==='customers'): ?><tr><td>#<?= e($r['cust_id']) ?></td><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td><td class="text-end"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#customer<?= $r['cust_id'] ?>">Edit</button><form class="d-inline" method="post" onsubmit="return confirm('Delete this customer? This may fail if the customer has order history.');"><input type="hidden" name="action" value="delete_customer"><input type="hidden" name="cust_id" value="<?= $r['cust_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
<div class="modal fade" id="customer<?= $r['cust_id'] ?>" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="update_customer"><input type="hidden" name="cust_id" value="<?= $r['cust_id'] ?>"><label class="form-label">Name</label><input class="form-control mb-3" name="name" required value="<?= e($r['name']) ?>"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required value="<?= e($r['email']) ?>"></div><div class="modal-footer"><button class="btn btn-brand">Save Changes</button></div></form></div></div>
<?php else: ?><tr><td>#<?= e($r['vendor_id']) ?></td><td><?= e($r['stall_name']) ?></td><td><?= e($r['email']) ?></td><td><?= e($r['operating_hours']) ?></td><td><?= status_badge($r['approval_status']) ?></td><td><?= status_badge($r['stall_status']) ?></td><td class="text-end"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#vendor<?= $r['vendor_id'] ?>">Manage</button><form class="d-inline" method="post" onsubmit="return confirm('Delete this vendor? This may fail if there are linked orders.');"><input type="hidden" name="action" value="delete_vendor"><input type="hidden" name="vendor_id" value="<?= $r['vendor_id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
<div class="modal fade" id="vendor<?= $r['vendor_id'] ?>" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Manage Vendor</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="update_vendor"><input type="hidden" name="vendor_id" value="<?= $r['vendor_id'] ?>"><label class="form-label">Stall Name</label><input class="form-control mb-2" name="stall_name" required value="<?= e($r['stall_name']) ?>"><label class="form-label">Email</label><input type="email" class="form-control mb-2" name="email" required value="<?= e($r['email']) ?>"><label class="form-label">Operating Hours</label><input class="form-control mb-2" name="operating_hours" required value="<?= e($r['operating_hours']) ?>"><div class="row"><div class="col"><label class="form-label">Approval</label><select class="form-select" name="approval_status"><?php foreach(['Pending','Approved','Rejected','Suspended'] as $s): ?><option <?= $r['approval_status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div><div class="col"><label class="form-label">Stall Status</label><select class="form-select" name="stall_status"><?php foreach(['Open','Closed'] as $s): ?><option <?= $r['stall_status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div></div></div><div class="modal-footer"><button class="btn btn-brand">Save Changes</button></div></form></div></div>
<?php endif; ?>
<?php endforeach; ?></tbody></table></div></section>
<?php admin_footer(); ?>
