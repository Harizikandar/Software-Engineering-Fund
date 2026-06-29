<?php

require "config.php";

header("Content-Type: application/json");

$vendor_id = intval($_GET["vendor_id"] ?? 0);

if ($vendor_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Missing vendor_id."
    ]);
    exit();
}

$stmt = $conn->prepare(
    "SELECT item_id, item_name, item_desc, category, price, image_path
     FROM items
     WHERE vendor_id = ? AND is_active = 1
     ORDER BY category, item_name"
);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($items as &$item) {
    $item["price"] = (float) $item["price"];
    $item["image_url"] = $item["image_path"]
        ? "/foodiesys/vendor/" . $item["image_path"]
        : null;
}
unset($item);

echo json_encode([
    "success" => true,
    "items" => $items
]);

$conn->close();

?>
