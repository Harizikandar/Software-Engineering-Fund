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

// ---------- Current month window ----------
$month_start = date('Y-m-01 00:00:00');
$month_end   = date('Y-m-t 23:59:59');
$month_label = date('F Y');

// ---------- Total sales (orders) + total revenue, current month ----------
$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT o.order_id) AS total_orders, COALESCE(SUM(p.amount), 0) AS total_revenue
     FROM orders o
     JOIN payments p ON p.order_id = o.order_id AND p.payment_status = 'Paid'
     WHERE o.vendor_id = ? AND o.order_status = 'Completed' AND o.created_at BETWEEN ? AND ?"
);
$stmt->bind_param("iss", $vendor_id, $month_start, $month_end);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_orders  = (int) ($totals['total_orders'] ?? 0);
$total_revenue = (float) ($totals['total_revenue'] ?? 0);

// ---------- Item-level breakdown, current month (only if there's data) ----------
$item_rows        = [];
$category_totals  = [];

if ($total_orders > 0) {
    $stmt = $conn->prepare(
        "SELECT i.category, i.item_name, SUM(od.quantity) AS qty_sold, SUM(od.subtotal) AS revenue
         FROM order_details od
         JOIN orders o ON od.order_id = o.order_id
         JOIN items i ON od.item_id = i.item_id
         WHERE o.vendor_id = ? AND o.order_status = 'Completed' AND o.created_at BETWEEN ? AND ?
         GROUP BY i.category, i.item_name
         ORDER BY i.category, revenue DESC"
    );
    $stmt->bind_param("iss", $vendor_id, $month_start, $month_end);
    $stmt->execute();
    $item_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($item_rows as $row) {
        $category_totals[$row['category']] = ($category_totals[$row['category']] ?? 0) + (float) $row['revenue'];
    }
}
$conn->close();

// ---------- Top 5 sellers by quantity ----------
$top_items = $item_rows;
usort($top_items, function ($a, $b) {
    return $b['qty_sold'] - $a['qty_sold'];
});
$top_items = array_slice($top_items, 0, 5);

// ---------- Group item rows by category for the details table (rowspan) ----------
$grouped = [];
foreach ($item_rows as $row) {
    $grouped[$row['category']][] = $row;
}
$table_total_revenue = array_sum(array_column($item_rows, 'revenue'));

// ---------- Fixed colour per menu category, matching the brand palette ----------
function categoryColor($category) {
    static $palette = [
        'Main Food' => '#0a47a8',
        'Beverages' => '#4fc3f7',
        'Desserts'  => '#f28cb1',
        'Snacks'    => '#f0c14b',
    ];
    return $palette[$category] ?? '#9e9e9e';
}

// ---------- Build SVG pie-slice paths from a [label => value] array ----------
function buildPieSlices(array $data, $cx, $cy, $r) {
    $total = array_sum($data);
    $slices = [];
    if ($total <= 0) return $slices;

    $startAngle = -90; // start at 12 o'clock
    foreach ($data as $label => $value) {
        if ($value <= 0) continue;

        $fraction  = $value / $total;
        $angle     = $fraction * 360;
        $endAngle  = $startAngle + $angle;

        if ($angle >= 359.99) {
            // Single category = full circle
            $path = "M $cx," . ($cy - $r) . " A $r,$r 0 1,1 " . ($cx - 0.01) . "," . ($cy - $r) . " Z";
        } else {
            $startRad = deg2rad($startAngle);
            $endRad   = deg2rad($endAngle);
            $x1 = round($cx + $r * cos($startRad), 2);
            $y1 = round($cy + $r * sin($startRad), 2);
            $x2 = round($cx + $r * cos($endRad), 2);
            $y2 = round($cy + $r * sin($endRad), 2);
            $largeArc = ($angle > 180) ? 1 : 0;
            $path = "M $cx,$cy L $x1,$y1 A $r,$r 0 $largeArc,1 $x2,$y2 Z";
        }

        $slices[] = [
            'label'   => $label,
            'percent' => $fraction * 100,
            'path'    => $path,
            'color'   => categoryColor($label),
        ];

        $startAngle = $endAngle;
    }
    return $slices;
}

$pie_slices = buildPieSlices($category_totals, 110, 110, 90);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics - Food Campus System</title>
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
    padding: 30px 28px 36px;
  }

  .btn {
    padding: 12px 28px;
    background-color: #0a47a8;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    font-family: inherit;
    transition: background-color 0.2s ease;
  }
  .btn:hover { background-color: #08368a; }

  .generate-wrap { text-align: center; margin-bottom: 28px; }

  .report-section { display: none; }
  .report-section.open { display: block; }

  .no-data {
    text-align: center;
    color: #555;
    padding: 30px 10px;
    font-size: 15px;
    background-color: #fff;
    border: 1px solid #c4c4c4;
    border-radius: 4px;
  }

  .stats-row { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
  .stat-card {
    flex: 1;
    min-width: 160px;
    background-color: #ffffff;
    border: 1px solid #c4c4c4;
    border-radius: 4px;
    padding: 18px 20px;
    text-align: center;
  }
  .stat-label { font-size: 13px; color: #555; font-weight: 700; margin-bottom: 2px; }
  .stat-sublabel { font-size: 11px; color: #888; margin-bottom: 10px; }
  .stat-value { font-size: 26px; font-weight: 800; color: #0a47a8; }

  .section-title {
    font-size: 17px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0 0 14px;
  }

  .chart-wrap {
    display: flex;
    gap: 28px;
    align-items: center;
    flex-wrap: wrap;
    background-color: #ffffff;
    border: 1px solid #c4c4c4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 30px;
  }
  .chart-svg { flex-shrink: 0; }

  .legend { display: flex; flex-direction: column; gap: 8px; }
  .legend-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #333; }
  .legend-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }

  .top-items-list {
    list-style: none;
    background-color: #ffffff;
    border: 1px solid #c4c4c4;
    border-radius: 4px;
    padding: 4px 0;
    margin-bottom: 30px;
  }
  .top-items-list li {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 18px;
    border-bottom: 1px solid #e6e6e6;
    font-size: 14px;
  }
  .top-items-list li:last-child { border-bottom: none; }
  .top-rank { font-weight: 700; color: #0a47a8; margin-right: 6px; }
  .top-item-right { color: #555; white-space: nowrap; font-size: 13px; }

  .table-wrap {
    overflow-x: auto;
    border: 1px solid #c4c4c4;
    border-radius: 4px;
  }
  table.sales-table { width: 100%; border-collapse: collapse; background-color: #ffffff; font-size: 13px; min-width: 480px; }
  table.sales-table th {
    background-color: #0a47a8;
    color: #ffffff;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
  }
  table.sales-table td { padding: 9px 12px; border-bottom: 1px solid #e0e8f5; color: #1a1a1a; }
  table.sales-table tbody tr:nth-child(even) td { background-color: #eef3fc; }
  table.sales-table tr.total-row td {
    background-color: #0a47a8 !important;
    color: #ffffff;
    font-weight: 700;
  }

  @media (max-width: 480px) {
    .content-card { margin: 0 12px 20px; padding: 24px 16px 30px; }
    .chart-wrap { flex-direction: column; align-items: flex-start; }
    .stat-value { font-size: 22px; }
  }
</style>
</head>
<body>

<div class="page">
  <div class="header">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Analytics</h1>
  </div>

  <div class="content-card">

    <div class="generate-wrap">
      <button type="button" class="btn" id="generateBtn" onclick="toggleReport()">Generate Report</button>
    </div>

    <div class="report-section" id="reportSection">

      <?php if ($total_orders === 0): ?>

        <p class="no-data">No sales data available yet.</p>

      <?php else: ?>

        <div class="stats-row">
          <div class="stat-card">
            <div class="stat-label">Total Sales</div>
            <div class="stat-sublabel">Orders completed in <?php echo $month_label; ?></div>
            <div class="stat-value"><?php echo $total_orders; ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-sublabel">Received in <?php echo $month_label; ?></div>
            <div class="stat-value">RM<?php echo number_format($total_revenue, 2); ?></div>
          </div>
        </div>

        <h3 class="section-title">Revenue by Category</h3>
        <div class="chart-wrap">
          <svg class="chart-svg" width="180" height="180" viewBox="0 0 220 220">
            <?php foreach ($pie_slices as $slice): ?>
              <path d="<?php echo $slice['path']; ?>" fill="<?php echo $slice['color']; ?>"></path>
            <?php endforeach; ?>
          </svg>
          <div class="legend">
            <?php foreach ($pie_slices as $slice): ?>
              <div class="legend-item">
                <span class="legend-dot" style="background-color: <?php echo $slice['color']; ?>;"></span>
                <span><?php echo htmlspecialchars($slice['label']); ?> &mdash; <?php echo number_format($slice['percent'], 1); ?>%</span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <h3 class="section-title">Top-Selling Items</h3>
        <ul class="top-items-list">
          <?php foreach ($top_items as $idx => $item): ?>
            <li>
              <span><span class="top-rank">#<?php echo $idx + 1; ?></span><?php echo htmlspecialchars($item['item_name']); ?> <span style="color:#888;">(<?php echo htmlspecialchars($item['category']); ?>)</span></span>
              <span class="top-item-right"><?php echo (int) $item['qty_sold']; ?> sold &middot; RM<?php echo number_format((float) $item['revenue'], 2); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <h3 class="section-title">Item Sales Details</h3>
        <div class="table-wrap">
          <table class="sales-table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Item</th>
                <th>Qty Sold</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($grouped as $category => $rows): ?>
                <?php $rowspan = count($rows); $first = true; ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <?php if ($first): ?>
                      <td rowspan="<?php echo $rowspan; ?>" style="font-weight:600; vertical-align: top;">
                        <?php echo htmlspecialchars($category); ?>
                      </td>
                      <?php $first = false; ?>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                    <td><?php echo (int) $row['qty_sold']; ?></td>
                    <td>RM<?php echo number_format((float) $row['revenue'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              <tr class="total-row">
                <td colspan="3">Total</td>
                <td>RM<?php echo number_format($table_total_revenue, 2); ?></td>
              </tr>
            </tbody>
          </table>
        </div>

      <?php endif; ?>

    </div>

  </div>
</div>

<script>
  function toggleReport() {
    const section = document.getElementById('reportSection');
    const btn = document.getElementById('generateBtn');
    const isOpen = section.classList.toggle('open');
    btn.textContent = isOpen ? 'Hide Report' : 'Generate Report';
  }
</script>

</body>
</html>
