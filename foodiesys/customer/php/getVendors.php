<?php

require "config.php";

header("Content-Type: application/json");

$result = $conn->query(
    "SELECT vendor_id, stall_name, operating_hours, stall_status, profile_image
     FROM vendors
     WHERE approval_status = 'Approved'
     ORDER BY stall_name"
);

$vendors = $result->fetch_all(MYSQLI_ASSOC);

foreach ($vendors as &$vendor) {
    $vendor["image_url"] = $vendor["profile_image"]
        ? "/foodiesys/vendor/" . $vendor["profile_image"]
        : null;
}
unset($vendor);

echo json_encode([
    "success" => true,
    "vendors" => $vendors
]);

$conn->close();

?>
