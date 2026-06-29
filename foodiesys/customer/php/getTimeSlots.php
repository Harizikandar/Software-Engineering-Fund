<?php

require "config.php";

header("Content-Type: application/json");

$result = $conn->query(
    "SELECT timeslot_id, time FROM time_slots WHERE is_available = 1 ORDER BY time"
);

$slots = $result->fetch_all(MYSQLI_ASSOC);

foreach ($slots as &$slot) {
    $slot["label"] = date("g:i A", strtotime($slot["time"]));
}
unset($slot);

echo json_encode([
    "success" => true,
    "slots" => $slots
]);

$conn->close();

?>
