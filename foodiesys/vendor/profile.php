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
// Once you build a login page, set $_SESSION['vendor_id'] there after authenticating.
// Hardcoded to 1 for now so you can test without a login flow.
$vendor_id = isset($_SESSION['vendor_id']) ? (int) $_SESSION['vendor_id'] : 1;

$update_message = "";
$message_type   = "";

// ---------- Handle the update (POST) ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stall_name      = trim($_POST['stall_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $operating_hours = trim($_POST['operating_hours'] ?? '');
    $stall_status    = $_POST['stall_status'] ?? 'Closed';

    // Only allow values that actually exist in the ENUM
    $allowed_status = ['Open', 'Closed', 'Inactive'];
    if (!in_array($stall_status, $allowed_status, true)) {
        $stall_status = 'Closed';
    }

    $stmt = $conn->prepare(
        "UPDATE vendors 
         SET stall_name = ?, email = ?, operating_hours = ?, stall_status = ? 
         WHERE vendor_id = ?"
    );
    $stmt->bind_param("ssssi", $stall_name, $email, $operating_hours, $stall_status, $vendor_id);

    if ($stmt->execute()) {
        $update_message = "Profile updated successfully!";
        $message_type   = "success";
    } else {
        $update_message = "Error updating profile: " . $conn->error;
        $message_type   = "error";
    }
    $stmt->close();
}

// ---------- Fetch current vendor data to display ----------
$stmt = $conn->prepare(
    "SELECT stall_name, email, operating_hours, stall_status 
     FROM vendors WHERE vendor_id = ?"
);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$result = $stmt->get_result();
$vendor = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$vendor) {
    // No matching row - show an empty form instead of a fatal error
    $vendor = [
        'stall_name'      => '',
        'email'           => '',
        'operating_hours' => '',
        'stall_status'    => 'Closed'
    ];
    if ($update_message === "") {
        $update_message = "No vendor record found for ID $vendor_id. Insert a row into the vendors table first.";
        $message_type   = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Profile - Food Campus System</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background-color: #0a47a8;
    min-height: 100vh;
  }

  .page {
    max-width: 700px;
    margin: 0 auto;
    min-height: 100vh;
  }

  .header { padding: 30px 28px 20px; }
  .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; }

  .content-card {
    background-color: #d9d9d9;
    margin: 0 20px;
    border-radius: 4px;
    padding: 30px 36px 40px;
  }

  .message {
    padding: 10px 14px;
    border-radius: 4px;
    margin-bottom: 20px;
    font-size: 14px;
  }
  .message.success { background-color: #d4edda; color: #1e7e34; }
  .message.error    { background-color: #f8d7da; color: #a71d2a; }

  .field-row { display: flex; align-items: center; margin-bottom: 18px; }

  .field-row label {
    width: 150px;
    font-size: 16px;
    color: #1a1a1a;
    flex-shrink: 0;
  }

  .field-row input,
  .field-row select {
    flex: 1;
    border: none;
    border-bottom: 1px solid #888;
    background: transparent;
    font-size: 15px;
    padding: 6px 6px;
    color: #1a1a1a;
    outline: none;
    font-family: inherit;
  }

  .field-row input:focus,
  .field-row select:focus { border-bottom: 2px solid #0a47a8; }

  /* View mode: disabled fields look like plain text, not a greyed-out form */
  .field-row input:disabled,
  .field-row select:disabled {
    border-bottom: none;
    background: transparent;
    color: #1a1a1a;
    opacity: 1;
    -webkit-text-fill-color: #1a1a1a;
    padding-left: 6px;
  }
  .field-row select:disabled {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    pointer-events: none;
  }

  .btn-outline {
    background-color: transparent;
    color: #0a47a8;
    border: 2px solid #0a47a8;
  }
  .btn-outline:hover { background-color: #e8edf8; }

  .button-group {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    margin-top: 36px;
  }

  .btn {
    width: 240px;
    padding: 14px 0;
    background-color: #0a47a8;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    transition: background-color 0.2s ease;
  }

  .btn:hover { background-color: #08368a; }

  @media (max-width: 480px) {
    .content-card { margin: 0 12px; padding: 24px 20px 30px; }
    .field-row { flex-direction: column; align-items: flex-start; }
    .field-row label { width: auto; margin-bottom: 4px; }
    .field-row input, .field-row select { width: 100%; }
    .btn { width: 100%; }
  }
</style>
</head>
<body>

<div class="page">
  <div class="header">
    <h1><?php echo htmlspecialchars($vendor['stall_name'] !== '' ? $vendor['stall_name'] : 'Vendor Name'); ?></h1>
  </div>

  <div class="content-card">

    <?php if ($update_message !== ""): ?>
      <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($update_message); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="profile.php" id="profileForm">

      <div class="field-row">
        <label for="stall_name">Stall Name:</label>
        <input type="text" id="stall_name" name="stall_name"
               value="<?php echo htmlspecialchars($vendor['stall_name']); ?>" required disabled>
      </div>

      <div class="field-row">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email"
               value="<?php echo htmlspecialchars($vendor['email']); ?>" required disabled>
      </div>

      <div class="field-row">
        <label for="operating_hours">Operating Hours:</label>
        <input type="text" id="operating_hours" name="operating_hours"
               value="<?php echo htmlspecialchars($vendor['operating_hours']); ?>"
               placeholder="e.g. 9:00 AM - 5:00 PM" required disabled>
      </div>

      <div class="field-row">
        <label for="stall_status">Stall Status:</label>
        <select id="stall_status" name="stall_status" disabled>
          <?php foreach (['Open', 'Closed', 'Inactive'] as $status): ?>
            <option value="<?php echo $status; ?>"
              <?php echo ($vendor['stall_status'] === $status) ? 'selected' : ''; ?>>
              <?php echo $status; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="button-group">
        <button type="button" class="btn" id="editBtn">Edit Profile</button>
        <button type="submit" class="btn" id="saveBtn" style="display:none;">Save Changes</button>
        <button type="button" class="btn btn-outline" id="cancelBtn" style="display:none;">Cancel</button>
        <a href="menu.php" class="btn">Menu</a>
      </div>

    </form>
  </div>
</div>

<script>
  const editBtn   = document.getElementById('editBtn');
  const saveBtn   = document.getElementById('saveBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  const fields    = document.querySelectorAll('#profileForm input, #profileForm select');

  function enterEditMode() {
    fields.forEach(field => field.disabled = false);
    editBtn.style.display   = 'none';
    saveBtn.style.display   = 'inline-block';
    cancelBtn.style.display = 'inline-block';
    if (fields.length) fields[0].focus();
  }

  editBtn.addEventListener('click', enterEditMode);

  cancelBtn.addEventListener('click', function () {
    // Discard changes and reload fresh values from the database
    window.location.href = 'profile.php';
  });

  <?php if ($message_type === 'error' && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  // If the last save attempt failed, reopen edit mode so the vendor doesn't lose context
  enterEditMode();
  <?php endif; ?>
</script>

</body>
</html>
