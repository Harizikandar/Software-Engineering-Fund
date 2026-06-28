<?php
session_start();

// ---------- Database connection ----------
$host     = "localhost";
$username = "root";
$password = "";
$database = "foodiesys";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------- Identify the logged-in vendor ----------
$vendor_id = isset($_SESSION['vendor_id']) ? (int) $_SESSION['vendor_id'] : 1;

// ---------- Fetch completed / cancelled orders for this vendor ----------
$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_status, o.created_at, c.name AS customer_name
     FROM orders o
     JOIN customers c ON o.cust_id = c.cust_id
     WHERE o.vendor_id = ? AND o.order_status IN ('Completed', 'Cancelled')
     ORDER BY o.created_at DESC"
);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- Reusable statements to pull each order's line items + payment ----------
$item_stmt = $conn->prepare(
    "SELECT i.item_name, od.quantity, od.subtotal
     FROM order_details od
     JOIN items i ON od.item_id = i.item_id
     WHERE od.order_id = ?"
);
$pay_stmt = $conn->prepare(
    "SELECT payment_method, payment_status, amount
     FROM payments WHERE order_id = ?"
);

foreach ($orders as &$order) {
    $item_stmt->bind_param("i", $order['order_id']);
    $item_stmt->execute();
    $order['items'] = $item_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $pay_stmt->bind_param("i", $order['order_id']);
    $pay_stmt->execute();
    $order['payment'] = $pay_stmt->get_result()->fetch_assoc();
}
unset($order);

$item_stmt->close();
$pay_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order History - Food Campus System</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background-color: #0a47a8;
    min-height: 100vh;
  }

  .page { max-width: 750px; margin: 0 auto; min-height: 100vh; }

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

  .empty-state { text-align: center; color: #555; padding: 30px 20px; }

  .order-row { padding: 14px 24px; border-bottom: 1px solid #b9b9b9; }
  .order-row:last-child { border-bottom: none; }

  .order-row-top,
  .order-row-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .order-row-bottom { margin-top: 6px; }

  .order-id-toggle {
    background: none;
    border: none;
    font-size: 16px;
    font-weight: 700;
    color: #1a1a1a;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .chevron { display: inline-block; transition: transform 0.15s ease; font-weight: 400; }
  .order-row.expanded .chevron { transform: rotate(90deg); }

  .order-date { font-size: 13px; color: #555; }

  .order-status { font-size: 14px; color: #666; }
  .order-status.status-cancelled { color: #a71d2a; }

  .see-more-btn {
    padding: 6px 18px;
    background-color: transparent;
    color: #0a47a8;
    border: 1px solid #0a47a8;
    border-radius: 4px;
    font-size: 13px;
    cursor: pointer;
    font-family: inherit;
  }
  .see-more-btn:hover { background-color: #e8edf8; }

  .order-details {
    display: none;
    margin-top: 14px;
    padding: 14px 16px;
    background-color: #ececec;
    border: 1px solid #c4c4c4;
    border-radius: 4px;
    font-size: 14px;
  }
  .order-row.expanded .order-details { display: block; }

  .order-details .row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
  }
  .order-details .row.total {
    border-top: 1px solid #c4c4c4;
    margin-top: 6px;
    padding-top: 8px;
    font-weight: 700;
  }
  .detail-label { color: #555; font-weight: 600; margin-bottom: 6px; }

  @media (max-width: 480px) {
    .order-row { padding: 12px 16px; }
    .order-date { font-size: 12px; }
  }
</style>
</head>
<body>

<div class="page">
  <div class="header">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Order History</h1>
  </div>

  <div class="content-card">

    <?php if (empty($orders)): ?>
      <p class="empty-state">No completed orders yet.</p>
    <?php endif; ?>

    <?php foreach ($orders as $order): ?>
      <?php
        $padded_id = str_pad((string) $order['order_id'], 5, '0', STR_PAD_LEFT);
        $formatted_date = date('j M Y', strtotime($order['created_at']));
        $status_class = ($order['order_status'] === 'Cancelled') ? 'status-cancelled' : '';
      ?>
      <div class="order-row" id="row-<?php echo $order['order_id']; ?>">

        <div class="order-row-top">
          <button type="button" class="order-id-toggle" onclick="toggleOrder(<?php echo $order['order_id']; ?>)">
            Order ID <?php echo $padded_id; ?> <span class="chevron">&rsaquo;</span>
          </button>
          <span class="order-date"><?php echo $formatted_date; ?></span>
        </div>

        <div class="order-row-bottom">
          <span class="order-status <?php echo $status_class; ?>">
            <?php echo htmlspecialchars($order['order_status']); ?>
          </span>
          <button type="button" class="see-more-btn" id="btn-<?php echo $order['order_id']; ?>"
                  onclick="toggleOrder(<?php echo $order['order_id']; ?>)">
            See more
          </button>
        </div>

        <div class="order-details">
          <div class="detail-label">Customer: <?php echo htmlspecialchars($order['customer_name']); ?></div>

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

          <?php if ($order['payment']): ?>
            <div class="row total">
              <span>Total Paid (<?php echo htmlspecialchars($order['payment']['payment_method']); ?>)</span>
              <span>RM<?php echo number_format((float) $order['payment']['amount'], 2); ?></span>
            </div>
          <?php endif; ?>
        </div>

      </div>
    <?php endforeach; ?>

  </div>
</div>

<script>
  function toggleOrder(orderId) {
    const row = document.getElementById('row-' + orderId);
    const btn = document.getElementById('btn-' + orderId);
    const isExpanded = row.classList.toggle('expanded');
    btn.textContent = isExpanded ? 'See less' : 'See more';
  }
</script>

</body>
</html>
