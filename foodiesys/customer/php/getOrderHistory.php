<?php

require "session.php";
require "config.php";

header("Content-Type: application/json");

if (!isset($_SESSION["cust_id"])) {
    echo json_encode([
        "success" => false,
        "message" => "Please login first."
    ]);
    exit();
}

$cust_id = $_SESSION["cust_id"];

$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_status, o.created_at, o.vendor_id, v.stall_name,
            p.payment_method, p.payment_status, p.amount
     FROM orders o
     JOIN vendors v ON o.vendor_id = v.vendor_id
     LEFT JOIN payments p ON p.order_id = o.order_id
     WHERE o.cust_id = ?
     ORDER BY o.created_at DESC"
);
$stmt->bind_param("i", $cust_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$item_stmt = $conn->prepare(
    "SELECT i.item_name, od.quantity, od.subtotal
     FROM order_details od
     JOIN items i ON od.item_id = i.item_id
     WHERE od.order_id = ?"
);

foreach ($orders as &$order) {
    $item_stmt->bind_param("i", $order["order_id"]);
    $item_stmt->execute();
    $order["items"] = $item_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $order["amount"] = $order["amount"] !== null ? (float) $order["amount"] : null;
}
unset($order);
$item_stmt->close();

echo json_encode([
    "success" => true,
    "orders" => $orders
]);

$conn->close();

?>
