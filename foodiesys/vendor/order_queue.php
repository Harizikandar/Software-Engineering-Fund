<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$vendor_id = (int) $_SESSION['vendor_id'];

$message      = "";
$message_type = "";

// ---------- Allowed status transitions (server-side source of truth) ----------
// 'Paid'            -> queue label "Pending"
// 'Preparing'       -> queue label "In Progress"
// 'Ready for Pickup' -> queue label "Ready to Pickup"
$allowed_transitions = [
    'Paid'             => ['Preparing', 'Cancelled'],
    'Preparing'        => ['Ready for Pickup', 'Cancelled'],
    'Ready for Pickup' => ['Completed'],
];

// ---------- Handle: update order status ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $order_id   = (int) ($_POST['order_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

    $check = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ? AND vendor_id = ?");
    $check->bind_param("ii", $order_id, $vendor_id);
    $check->execute();
    $current = $check->get_result()->fetch_assoc();
    $check->close();

    $padded_id = str_pad((string) $order_id, 5, '0', STR_PAD_LEFT);

    if ($current && in_array($new_status, $allowed_transitions[$current['order_status']] ?? [], true)) {
        $upd = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ? AND vendor_id = ?");
        $upd->bind_param("sii", $new_status, $order_id, $vendor_id);
        $upd->execute();
        $upd->close();

        // Cash orders are paid at pickup, so settle the payment record once
        // the order is handed over. QR orders are already marked Paid up front.
        if ($new_status === 'Completed') {
            $pay_upd = $conn->prepare(
                "UPDATE payments SET payment_status = 'Paid'
                 WHERE order_id = ? AND vendor_id = ? AND payment_method = 'Cash' AND payment_status = 'Pending Payment'"
            );
            $pay_upd->bind_param("ii", $order_id, $vendor_id);
            $pay_upd->execute();
            $pay_upd->close();
        }

        $friendly = [
            'Preparing'        => 'moved to In Progress',
            'Ready for Pickup' => 'marked Ready to Pickup',
            'Completed'        => 'marked as picked up',
            'Cancelled'        => 'cancelled',
        ];
        $message      = "Order #$padded_id " . ($friendly[$new_status] ?? "updated to \"$new_status\"") . ".";
        $message_type = "success";
    } else {
        $message      = "Could not update order #$padded_id — invalid status change.";
        $message_type = "error";
    }
}

// ---------- Fetch active orders for this vendor (Paid / Preparing / Ready for Pickup) ----------
// Oldest first, so the vendor always sees who has been waiting longest at the top.
$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_status, o.created_at, c.name AS customer_name, t.time AS pickup_time
     FROM orders o
     JOIN customers c ON o.cust_id = c.cust_id
     LEFT JOIN time_slots t ON o.timeslot_id = t.timeslot_id
     WHERE o.vendor_id = ? AND o.order_status IN ('Paid', 'Preparing', 'Ready for Pickup')
     ORDER BY o.created_at ASC"
);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- Pull line items for each order (so the vendor can see what to prepare) ----------
$item_stmt = $conn->prepare(
    "SELECT i.item_name, od.quantity, od.subtotal
     FROM order_details od
     JOIN items i ON od.item_id = i.item_id
     WHERE od.order_id = ?"
);
foreach ($orders as &$order) {
    $item_stmt->bind_param("i", $order['order_id']);
    $item_stmt->execute();
    $order['items'] = $item_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
unset($order);
$item_stmt->close();
$conn->close();

// ---------- Map DB status to the queue's display label + colour ----------
function queueBadge($status) {
    switch ($status) {
        case 'Paid':
            return ['label' => 'Pending', 'class' => 'badge-pending'];
        case 'Preparing':
            return ['label' => 'In Progress', 'class' => 'badge-progress'];
        case 'Ready for Pickup':
            return ['label' => 'Ready to Pickup', 'class' => 'badge-ready'];
        default:
            return ['label' => $status, 'class' => 'badge-pending'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Queue - Food Campus System</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background-color: #0a47a8;
    min-height: 100vh;
  }

  .page { max-width: 750px; margin: 0 auto; min-height: 100vh; }

  .logo-container { text-align: center; padding: 24px 0 4px; }
  .logo-container img { width: 120px; }

  .header { padding: 24px 28px 20px; }
  .back-link { color: #cfe0ff; font-size: 14px; text-decoration: none; }
  .back-link:hover { text-decoration: underline; }
  .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin-top: 8px; }

  .content-card {
    background-color: #d9d9d9;
    margin: 0 20px 30px;
    border-radius: 4px;
    padding: 10px 0;
    overflow: hidden;
  }

  .message {
    margin: 16px 24px 0;
    padding: 10px 14px;
    border-radius: 4px;
    font-size: 14px;
  }
  .message.success { background-color: #d4edda; color: #1e7e34; }
  .message.error    { background-color: #f8d7da; color: #a71d2a; }

  .empty-state { text-align: center; color: #555; padding: 30px 20px; }

  .order-row { padding: 16px 24px; border-bottom: 1px solid #b9b9b9; }
  .order-row:last-child { border-bottom: none; }

  .order-row-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
  }
  .order-id { font-size: 16px; font-weight: 700; color: #1a1a1a; }

  .status-group { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
  .status-label { font-size: 14px; font-weight: 600; color: #1a1a1a; white-space: nowrap; }
  .status-swatch { width: 46px; height: 32px; border-radius: 4px; flex-shrink: 0; }
  .status-swatch.badge-pending  { background-color: #1a1a1a; }
  .status-swatch.badge-progress { background-color: #f0db4f; }
  .status-swatch.badge-ready    { background-color: #3fc47a; }

  .order-row-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
  }
  .pickup-time { font-size: 13px; color: #555; }

  .manage-btn {
    background: none;
    border: 1px solid #0a47a8;
    color: #0a47a8;
    padding: 6px 14px;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .manage-btn:hover { background-color: #e8edf8; }

  .chevron { display: inline-block; transition: transform 0.15s ease; font-weight: 400; }
  .order-row.expanded .chevron { transform: rotate(90deg); }

  .manage-panel {
    display: none;
    margin-top: 14px;
    padding: 14px 16px;
    background-color: #ececec;
    border: 1px solid #c4c4c4;
    border-radius: 4px;
    font-size: 14px;
  }
  .order-row.expanded .manage-panel { display: block; }

  .detail-label { color: #555; font-weight: 600; margin-bottom: 8px; }

  .items-list .row {
    display: flex;
    justify-content: space-between;
    padding: 3px 0;
  }

  .status-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #c4c4c4;
  }
  .status-actions form { display: inline; }

  .btn {
    padding: 8px 18px;
    background-color: #0a47a8;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
    font-family: inherit;
  }
  .btn:hover { background-color: #08368a; }

  .btn-danger {
    background-color: transparent;
    color: #a71d2a;
    border: 1px solid #a71d2a;
    padding: 8px 18px;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
    font-family: inherit;
  }
  .btn-danger:hover { background-color: #fbe7e9; }

  .note-text { font-size: 13px; color: #555; font-style: italic; }

  @media (max-width: 480px) {
    .order-row { padding: 14px 16px; }
    .order-id { font-size: 15px; }
    .status-label { font-size: 13px; }
    .status-swatch { width: 38px; height: 28px; }
    .order-row-meta { flex-direction: column; align-items: flex-start; gap: 10px; }
  }
</style>
</head>
<body>

<div class="logo-container">
  <img src="assets/images/logo.png" alt="FoodieSys">
</div>

<div class="page">
  <div class="header">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Order Queue</h1>
  </div>

  <div class="content-card">

    <?php if ($message !== ""): ?>
      <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
      <p class="empty-state">No active orders right now.</p>
    <?php endif; ?>

    <?php foreach ($orders as $order): ?>
      <?php
        $padded_id = str_pad((string) $order['order_id'], 5, '0', STR_PAD_LEFT);
        $badge     = queueBadge($order['order_status']);
        $pickup_display = $order['pickup_time']
            ? date('g:i A', strtotime($order['pickup_time']))
            : 'Not set';
        $next_options = $allowed_transitions[$order['order_status']] ?? [];
      ?>
      <div class="order-row" id="row-<?php echo $order['order_id']; ?>">

        <div class="order-row-top">
          <div class="order-id">Order ID <?php echo $padded_id; ?></div>
          <div class="status-group">
            <span class="status-label"><?php echo htmlspecialchars($badge['label']); ?></span>
            <span class="status-swatch <?php echo $badge['class']; ?>"></span>
          </div>
        </div>

        <div class="order-row-meta">
          <span class="pickup-time">Pickup: <?php echo htmlspecialchars($pickup_display); ?></span>
          <button type="button" class="manage-btn" id="managebtn-<?php echo $order['order_id']; ?>"
                  onclick="toggleManage(<?php echo $order['order_id']; ?>)">
            Manage Status <span class="chevron">&rsaquo;</span>
          </button>
        </div>

        <div class="manage-panel">
          <div class="detail-label">Customer: <?php echo htmlspecialchars($order['customer_name']); ?></div>

          <div class="items-list">
            <?php if (!empty($order['items'])): ?>
              <?php foreach ($order['items'] as $line): ?>
                <div class="row">
                  <span><?php echo htmlspecialchars($line['item_name']); ?> &times; <?php echo (int) $line['quantity']; ?></span>
                  <span>RM<?php echo number_format((float) $line['subtotal'], 2); ?></span>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="row"><span>No item details recorded for this order.</span></div>
            <?php endif; ?>
          </div>

          <div class="status-actions">
            <?php if (in_array('Preparing', $next_options, true)): ?>
              <form method="POST" action="order_queue.php">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                <input type="hidden" name="new_status" value="Preparing">
                <button type="submit" class="btn">Start Preparing</button>
              </form>
            <?php endif; ?>

            <?php if (in_array('Ready for Pickup', $next_options, true)): ?>
              <form method="POST" action="order_queue.php">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                <input type="hidden" name="new_status" value="Ready for Pickup">
                <button type="submit" class="btn">Mark Ready for Pickup</button>
              </form>
            <?php endif; ?>

            <?php if (in_array('Completed', $next_options, true)): ?>
              <form method="POST" action="order_queue.php">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                <input type="hidden" name="new_status" value="Completed">
                <button type="submit" class="btn">Mark Picked Up</button>
              </form>
            <?php endif; ?>

            <?php if (in_array('Cancelled', $next_options, true)): ?>
              <form method="POST" action="order_queue.php" onsubmit="return confirm('Cancel this order? The customer will need to be notified.');">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                <input type="hidden" name="new_status" value="Cancelled">
                <button type="submit" class="btn-danger">Cancel Order</button>
              </form>
            <?php endif; ?>

            <?php if (empty($next_options)): ?>
              <span class="note-text">No further action available for this order.</span>
            <?php endif; ?>
          </div>
        </div>

      </div>
    <?php endforeach; ?>

  </div>
</div>

<script>
  function toggleManage(orderId) {
    const row = document.getElementById('row-' + orderId);
    const btn = document.getElementById('managebtn-' + orderId);
    const expanded = row.classList.toggle('expanded');
    btn.innerHTML = expanded
      ? 'Hide Status <span class="chevron">&rsaquo;</span>'
      : 'Manage Status <span class="chevron">&rsaquo;</span>';
  }
</script>

</body>
</html>
