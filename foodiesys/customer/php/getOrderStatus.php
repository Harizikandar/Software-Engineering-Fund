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

$order_id = intval($_GET["order_id"] ?? 0);
$cust_id = $_SESSION["cust_id"];

$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_status, o.created_at, v.stall_name, t.time AS pickup_time,
            p.payment_method, p.payment_status, p.amount
     FROM orders o
     JOIN vendors v ON o.vendor_id = v.vendor_id
     LEFT JOIN time_slots t ON o.timeslot_id = t.timeslot_id
     LEFT JOIN payments p ON p.order_id = o.order_id
     WHERE o.order_id = ? AND o.cust_id = ?"
);
$stmt->bind_param("ii", $order_id, $cust_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode([
        "success" => false,
        "message" => "Order not found."
    ]);
    exit();
}

$item_stmt = $conn->prepare(
    "SELECT i.item_name, od.quantity, od.subtotal
     FROM order_details od
     JOIN items i ON od.item_id = i.item_id
     WHERE od.order_id = ?"
);
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$order["items"] = $item_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$item_stmt->close();

$order["pickup_time"] = $order["pickup_time"] ? date("g:i A", strtotime($order["pickup_time"])) : null;
$order["amount"] = $order["amount"] !== null ? (float) $order["amount"] : null;

echo json_encode([
    "success" => true,
    "order" => $order
]);

$conn->close();

?>
