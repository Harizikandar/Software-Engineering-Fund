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

$message       = "";
$message_type  = "";
$stay_in_edit  = false; // controls whether the page reopens in edit mode after a POST

// Allowed categories for the dropdown - adjust to match your menu
$categories = ['Main Food', 'Beverages', 'Desserts', 'Snacks'];

// ---------- Handle: Add new item ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_item') {
    $stay_in_edit = true;

    $item_name = trim($_POST['item_name'] ?? '');
    $item_desc = trim($_POST['item_desc'] ?? '');
    $price_raw = trim($_POST['price'] ?? '');
    $category  = $_POST['category'] ?? 'Main Food';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!in_array($category, $categories, true)) {
        $category = 'Main Food';
    }

    $errors = [];

    if ($item_name === '') {
        $errors[] = "Item name is required.";
    } elseif (mb_strlen($item_name) > 50) {
        $errors[] = "Item name must be 50 characters or fewer.";
    }

    if ($price_raw === '' || !is_numeric($price_raw) || (float) $price_raw < 0) {
        $errors[] = "Price must be a valid positive number.";
    }
    $price = (float) $price_raw;

    // ---- Image upload ----
    $image_path = null;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $file_type = mime_content_type($_FILES['item_image']['tmp_name']);

        if (!in_array($file_type, $allowed_types, true)) {
            $errors[] = "Image must be a JPG, PNG, GIF, or WEBP file.";
        } else {
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $ext      = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
            $filename = 'item_' . uniqid() . '.' . $ext;

            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_dir . $filename)) {
                $image_path = 'uploads/' . $filename;
            } else {
                $errors[] = "Failed to upload image. Check folder permissions.";
            }
        }
    } else {
        $errors[] = "An item image is required.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO items (item_name, item_desc, category, price, image_path, is_active, vendor_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssdsii", $item_name, $item_desc, $category, $price, $image_path, $is_active, $vendor_id);

        if ($stmt->execute()) {
            $message      = "Item added successfully!";
            $message_type = "success";
        } else {
            $message      = "Error adding item: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    } else {
        $message      = implode(' ', $errors);
        $message_type = "error";
    }
}

// ---------- Handle: Delete item ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_item') {
    $stay_in_edit = true;
    $item_id = (int) ($_POST['item_id'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $item_id, $vendor_id);
    $stmt->execute();
    $stmt->close();

    $message      = "Item removed.";
    $message_type = "success";
}

// ---------- Handle: Toggle availability ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    $stay_in_edit = true;
    $item_id = (int) ($_POST['item_id'] ?? 0);

    $stmt = $conn->prepare("UPDATE items SET is_active = NOT is_active WHERE item_id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $item_id, $vendor_id);
    $stmt->execute();
    $stmt->close();
}

// ---------- Fetch items, grouped by category ----------
$stmt = $conn->prepare(
    "SELECT item_id, item_name, item_desc, category, price, image_path, is_active
     FROM items WHERE vendor_id = ? ORDER BY category, item_name"
);
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$result = $stmt->get_result();

$items_by_category = [];
while ($row = $result->fetch_assoc()) {
    $items_by_category[$row['category']][] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menu - Food Campus System</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background-color: #0a47a8;
    min-height: 100vh;
  }

  .page { max-width: 750px; margin: 0 auto; min-height: 100vh; }

  .header { padding: 24px 28px 20px; }
  .back-link {
    color: #cfe0ff;
    font-size: 14px;
    text-decoration: none;
  }
  .back-link:hover { text-decoration: underline; }
  .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin-top: 8px; }

  .content-card {
    background-color: #d9d9d9;
    margin: 0 20px 30px;
    border-radius: 4px;
    padding: 28px 32px 36px;
  }

  .message {
    padding: 10px 14px;
    border-radius: 4px;
    margin-bottom: 20px;
    font-size: 14px;
  }
  .message.success { background-color: #d4edda; color: #1e7e34; }
  .message.error    { background-color: #f8d7da; color: #a71d2a; }

  .top-actions { display: flex; justify-content: center; margin-bottom: 26px; }

  .btn {
    padding: 12px 28px;
    background-color: #0a47a8;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    font-size: 15px;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    transition: background-color 0.2s ease;
  }
  .btn:hover { background-color: #08368a; }

  .btn-small {
    padding: 6px 14px;
    font-size: 13px;
  }

  .btn-outline {
    background-color: transparent;
    color: #0a47a8;
    border: 1px solid #0a47a8;
  }
  .btn-outline:hover { background-color: #e8edf8; }

  .btn-danger {
    background-color: transparent;
    color: #a71d2a;
    border: 1px solid #a71d2a;
  }
  .btn-danger:hover { background-color: #fbe7e9; }

  .category-box {
    border: 1px solid #999;
    border-radius: 4px;
    padding: 18px 18px 22px;
    margin-bottom: 20px;
    background-color: #d9d9d9;
  }
  .category-box h3 { font-size: 18px; margin-bottom: 14px; color: #1a1a1a; }

  .item-box {
    border: 1px solid #999;
    border-radius: 4px;
    background-color: #d9d9d9;
    padding: 14px 16px;
    margin-bottom: 14px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
  }

  .item-info { flex: 1; min-width: 0; }
  .item-info h4 { font-size: 16px; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
  .item-info p {
    font-size: 13px;
    color: #444;
    line-height: 1.4;
    margin-bottom: 10px;
  }
  .item-price { font-size: 15px; font-weight: 600; color: #1a1a1a; }

  .tag-unavailable {
    font-size: 11px;
    background-color: #a71d2a;
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: normal;
  }

  .item-image {
    width: 90px;
    height: 90px;
    border-radius: 4px;
    object-fit: cover;
    flex-shrink: 0;
    border: 1px solid #aaa;
  }
  .item-image-placeholder {
    width: 90px;
    height: 90px;
    border-radius: 4px;
    background-color: #c4c4c4;
    flex-shrink: 0;
    border: 1px solid #aaa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    color: #666;
    text-align: center;
  }

  .item-controls {
    display: flex;
    gap: 8px;
    margin-top: 8px;
  }
  .item-controls form { display: inline; }

  .edit-only { display: none; }
  body.editing .edit-only { display: block; }
  body.editing .item-controls { display: flex; }
  body.editing #editMenuBtn { display: none; }
  body.editing #doneEditingBtn { display: inline-block; }

  .add-item-form {
    border: 2px dashed #0a47a8;
    border-radius: 4px;
    padding: 20px;
    margin-top: 10px;
    background-color: #eef2fa;
  }
  .add-item-form h3 { margin-bottom: 16px; font-size: 17px; }

  .form-row { margin-bottom: 14px; }
  .form-row label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 5px;
    color: #1a1a1a;
  }
  .form-row input[type="text"],
  .form-row input[type="number"],
  .form-row select,
  .form-row textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #aaa;
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
    background-color: #fff;
  }
  .form-row textarea { resize: vertical; min-height: 50px; }
  .char-count { font-size: 12px; color: #666; margin-top: 3px; text-align: right; }

  .checkbox-row { display: flex; align-items: center; gap: 8px; }
  .checkbox-row input { width: auto; }
  .checkbox-row label { margin-bottom: 0; }

  #doneEditingBtn { display: none; }

  @media (max-width: 480px) {
    .content-card { margin: 0 12px 20px; padding: 20px 18px 28px; }
    .item-box { flex-direction: column; }
    .item-image, .item-image-placeholder { width: 100%; height: 140px; }
  }
</style>
</head>
<body id="pageBody" class="<?php echo $stay_in_edit ? 'editing' : ''; ?>">

<div class="page">
  <div class="header">
    <a href="profile.php" class="back-link">&larr; Back to Profile</a>
    <h1>Menu</h1>
  </div>

  <div class="content-card">

    <?php if ($message !== ""): ?>
      <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <div class="top-actions">
      <button type="button" class="btn" id="editMenuBtn">Edit Menu</button>
      <button type="button" class="btn btn-outline" id="doneEditingBtn">Done Editing</button>
    </div>

    <?php if (empty($items_by_category)): ?>
      <p style="text-align:center; color:#555;">No menu items yet. Click "Edit Menu" to add your first item.</p>
    <?php endif; ?>

    <?php foreach ($items_by_category as $category => $items): ?>
      <div class="category-box">
        <h3><?php echo htmlspecialchars($category); ?></h3>

        <?php foreach ($items as $item): ?>
          <div class="item-box">
            <div class="item-info">
              <h4>
                <?php echo htmlspecialchars($item['item_name']); ?>
                <?php if (!$item['is_active']): ?>
                  <span class="tag-unavailable">Sold Out</span>
                <?php endif; ?>
              </h4>
              <p><?php echo htmlspecialchars($item['item_desc']); ?></p>
              <div class="item-price">RM<?php echo number_format((float) $item['price'], 2); ?></div>

              <div class="item-controls edit-only">
                <form method="POST" action="menu.php">
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="item_id" value="<?php echo (int) $item['item_id']; ?>">
                  <button type="submit" class="btn btn-outline btn-small">
                    <?php echo $item['is_active'] ? 'Mark Sold Out' : 'Mark Available'; ?>
                  </button>
                </form>
                <form method="POST" action="menu.php" onsubmit="return confirm('Remove this item from your menu?');">
                  <input type="hidden" name="action" value="delete_item">
                  <input type="hidden" name="item_id" value="<?php echo (int) $item['item_id']; ?>">
                  <button type="submit" class="btn btn-danger btn-small">Remove</button>
                </form>
              </div>
            </div>

            <?php if (!empty($item['image_path'])): ?>
              <img class="item-image" src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
            <?php else: ?>
              <div class="item-image-placeholder">No Image</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <div class="edit-only">
      <div class="add-item-form">
        <h3>Add New Item</h3>
        <form method="POST" action="menu.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add_item">

          <div class="form-row">
            <label for="item_name">Item Name (max 50 characters)</label>
            <input type="text" id="item_name" name="item_name" maxlength="50" required oninput="document.getElementById('charCount').textContent = this.value.length;">
            <div class="char-count"><span id="charCount">0</span>/50</div>
          </div>

          <div class="form-row">
            <label for="category">Category</label>
            <select id="category" name="category">
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row">
            <label for="item_desc">Item Description</label>
            <textarea id="item_desc" name="item_desc" placeholder="e.g. Spicy"></textarea>
          </div>

          <div class="form-row">
            <label for="price">Price (RM)</label>
            <input type="number" id="price" name="price" step="0.01" min="0" placeholder="9.50" required>
          </div>

          <div class="form-row">
            <label for="item_image">Item Image</label>
            <input type="file" id="item_image" name="item_image" accept="image/jpeg,image/png,image/webp,image/gif" required>
          </div>

          <div class="form-row checkbox-row">
            <input type="checkbox" id="is_active" name="is_active" checked>
            <label for="is_active">Available for order</label>
          </div>

          <button type="submit" class="btn" style="margin-top: 8px;">Add Item</button>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
  const body          = document.getElementById('pageBody');
  const editMenuBtn    = document.getElementById('editMenuBtn');
  const doneEditingBtn = document.getElementById('doneEditingBtn');

  editMenuBtn.addEventListener('click', function () {
    body.classList.add('editing');
  });

  doneEditingBtn.addEventListener('click', function () {
    body.classList.remove('editing');
  });
</script>

</body>
</html>
