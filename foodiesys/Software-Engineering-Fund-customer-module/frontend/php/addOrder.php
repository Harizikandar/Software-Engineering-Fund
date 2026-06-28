<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require "config.php";

header("Content-Type: application/json");

if (!isset($_SESSION["cust_id"])) {

    echo json_encode([
        "success" => false,
        "message" => "Please login first."
    ]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {

    echo json_encode([
        "success" => false,
        "message" => "No data received."
    ]);
    exit();
}

$cust_id = $_SESSION["cust_id"];
$vendor_id = intval($data["vendor_id"]);
$timeslot_id = intval($data["timeslot_id"]);
$payment_method = $data["payment_method"];
$cart = $data["cart"];

$order_status = "Preparing";

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("
        INSERT INTO orders
        (order_status, created_at, cust_id, vendor_id, timeslot_id)
        VALUES (?, NOW(), ?, ?, ?)
    ");

    $stmt->bind_param(
        "siii",
        $order_status,
        $cust_id,
        $vendor_id,
        $timeslot_id
    );

    $stmt->execute();

    $order_id = $conn->insert_id;

    $total = 0;

    foreach ($cart as $item) {

        $subtotal = $item["price"] * $item["quantity"];
        $total += $subtotal;

        $detail = $conn->prepare("
            INSERT INTO order_details
            (order_id, item_id, quantity, subtotal)
            VALUES (?, ?, ?, ?)
        ");

        $detail->bind_param(
            "iiid",
            $order_id,
            $item["itemId"],
            $item["quantity"],
            $subtotal
        );

        $detail->execute();
    }

    $payment_status = ($payment_method == "Cash")
        ? "Pending Payment"
        : "Paid";

    $payment = $conn->prepare("
        INSERT INTO payments
        (order_id, cust_id, vendor_id, payment_method, payment_status, amount, payment_date)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $payment->bind_param(
        "iiissd",
        $order_id,
        $cust_id,
        $vendor_id,
        $payment_method,
        $payment_status,
        $total
    );

    $payment->execute();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "order_id" => $order_id
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();

?>