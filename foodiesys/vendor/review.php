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

$message      = "";
$message_type = "";

// ---------- Rating filter (from the "Filter" button) ----------
$rating_filter = isset($_GET['rating']) ? (int) $_GET['rating'] : 0; // 0 = All
if ($rating_filter < 0 || $rating_filter > 5) {
    $rating_filter = 0;
}
$filter_qs = $rating_filter ? '?rating=' . $rating_filter : '';

// ---------- Handle: submit / edit a reply ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_reply') {
    $review_id  = (int) ($_POST['review_id'] ?? 0);
    $reply_text = trim($_POST['reply_text'] ?? '');

    if ($reply_text === '') {
        $message      = "Reply cannot be empty.";
        $message_type = "error";
    } elseif (mb_strlen($reply_text) > 1000) {
        $message      = "Reply must be 1000 characters or fewer.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare(
            "UPDATE reviews SET vendor_reply = ?, replied_at = NOW()
             WHERE review_id = ? AND vendor_id = ?"
        );
        $stmt->bind_param("sii", $reply_text, $review_id, $vendor_id);
        $stmt->execute();
        $stmt->close();

        $message      = "Reply published.";
        $message_type = "success";
    }
}

// ---------- Fetch reviews for this vendor (newest first) ----------
if ($rating_filter >= 1) {
    $stmt = $conn->prepare(
        "SELECT r.review_id, r.rating, r.comment, r.vendor_reply, r.replied_at, r.created_at, r.order_id,
                c.name AS customer_name
         FROM reviews r
         JOIN customers c ON r.cust_id = c.cust_id
         WHERE r.vendor_id = ? AND r.review_status = 'Visible' AND r.rating = ?
         ORDER BY r.created_at DESC"
    );
    $stmt->bind_param("ii", $vendor_id, $rating_filter);
} else {
    $stmt = $conn->prepare(
        "SELECT r.review_id, r.rating, r.comment, r.vendor_reply, r.replied_at, r.created_at, r.order_id,
                c.name AS customer_name
         FROM reviews r
         JOIN customers c ON r.cust_id = c.cust_id
         WHERE r.vendor_id = ? AND r.review_status = 'Visible'
         ORDER BY r.created_at DESC"
    );
    $stmt->bind_param("i", $vendor_id);
}
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// ---------- Mask the customer's name for privacy (e.g. "Hannah" -> "H****h") ----------
function maskName($name) {
    $name = trim($name);
    $len  = mb_strlen($name);
    if ($len === 0) return 'Customer';
    if ($len <= 2)  return mb_substr($name, 0, 1) . str_repeat('*', $len);
    return mb_substr($name, 0, 1) . str_repeat('*', $len - 2) . mb_substr($name, -1, 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shop Reviews - Food Campus System</title>
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
    padding: 24px 24px 10px;
  }

  .message {
    padding: 10px 14px;
    border-radius: 4px;
    margin-bottom: 18px;
    font-size: 14px;
  }
  .message.success { background-color: #d4edda; color: #1e7e34; }
  .message.error    { background-color: #f8d7da; color: #a71d2a; }

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
  .btn-small { padding: 6px 14px; font-size: 13px; }

  .btn-outline {
    background-color: transparent;
    color: #0a47a8;
    border: 1px solid #0a47a8;
  }
  .btn-outline:hover { background-color: #e8edf8; }

  .filter-wrap { margin-bottom: 22px; }
  .filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .filter-btn svg { width: 14px; height: 14px; }

  .filter-panel {
    display: none;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
    padding: 12px;
    background-color: #ececec;
    border: 1px solid #c4c4c4;
    border-radius: 4px;
  }
  .filter-panel.open { display: flex; }

  .filter-chip {
    padding: 6px 14px;
    border-radius: 16px;
    border: 1px solid #0a47a8;
    color: #0a47a8;
    font-size: 13px;
    text-decoration: none;
    background-color: #fff;
  }
  .filter-chip:hover { background-color: #e8edf8; }
  .filter-chip.active { background-color: #0a47a8; color: #ffffff; }

  .empty-state { text-align: center; color: #555; padding: 24px 0 30px; }

  .review-row {
    background-color: #d9d9d9;
    border: 1px solid #b9b9b9;
    border-radius: 4px;
    padding: 16px 18px;
    margin-bottom: 16px;
  }

  .review-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
  }

  .reviewer-info { display: flex; align-items: center; gap: 12px; }

  .avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #cfe0ff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .avatar-circle svg { width: 22px; height: 22px; fill: #0a47a8; }

  .reviewer-text { display: flex; flex-direction: column; }
  .reviewer-name { font-weight: 700; font-size: 14px; color: #1a1a1a; }
  .order-ref { font-size: 12px; color: #555; margin-top: 2px; }

  .review-date { font-size: 13px; color: #555; white-space: nowrap; }

  .review-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
  }

  .stars { font-size: 18px; letter-spacing: 2px; }
  .star-filled { color: #f0db4f; }
  .star-empty  { color: #bcbcbc; }

  .review-comment {
    font-size: 14px;
    color: #333;
    margin-top: 10px;
    line-height: 1.4;
  }

  .vendor-reply-display {
    margin-top: 12px;
    padding: 10px 12px;
    background-color: #e8edf8;
    border-left: 3px solid #0a47a8;
    border-radius: 0 4px 4px 0;
    font-size: 13px;
  }
  .reply-label { font-weight: 700; color: #0a47a8; display: block; margin-bottom: 4px; }
  .vendor-reply-display p { color: #333; line-height: 1.4; }

  .reply-form-wrap { display: none; margin-top: 12px; }
  .reply-form-wrap.open { display: block; }

  .reply-form-wrap textarea {
    width: 100%;
    min-height: 70px;
    padding: 8px 10px;
    border: 1px solid #aaa;
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    background-color: #fff;
  }
  .char-count { font-size: 12px; color: #666; margin-top: 3px; text-align: right; }

  .reply-form-actions { display: flex; gap: 10px; margin-top: 10px; }

  @media (max-width: 480px) {
    .content-card { margin: 0 12px 20px; padding: 20px 16px 10px; }
    .review-top { flex-direction: column; gap: 6px; }
    .review-date { align-self: flex-start; }
  }
</style>
</head>
<body>

<div class="page">
  <div class="header">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Shop Reviews</h1>
  </div>

  <div class="content-card">

    <?php if ($message !== ""): ?>
      <div class="message <?php echo $message_type; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <div class="filter-wrap">
      <button type="button" class="btn filter-btn" id="filterBtn" onclick="toggleFilterPanel()">
        <svg viewBox="0 0 24 24" fill="#ffffff"><path d="M3 4h18v2l-7 8v6l-4-2v-4L3 6V4z"/></svg>
        <?php echo $rating_filter ? "Filter: {$rating_filter}\xE2\x98\x85" : "Filter"; ?>
      </button>

      <div class="filter-panel <?php echo $rating_filter ? 'open' : ''; ?>" id="filterPanel">
        <a href="review.php" class="filter-chip <?php echo $rating_filter === 0 ? 'active' : ''; ?>">All</a>
        <?php for ($s = 5; $s >= 1; $s--): ?>
          <a href="review.php?rating=<?php echo $s; ?>" class="filter-chip <?php echo $rating_filter === $s ? 'active' : ''; ?>"><?php echo $s; ?>&#9733;</a>
        <?php endfor; ?>
      </div>
    </div>

    <?php if (empty($reviews)): ?>
      <p class="empty-state"><?php echo $rating_filter ? "No reviews match this filter." : "No reviews yet."; ?></p>
    <?php endif; ?>

    <?php foreach ($reviews as $review): ?>
      <?php
        $rating       = max(0, min(5, (int) $review['rating']));
        $masked_name  = maskName($review['customer_name']);
        $review_date  = date('j M Y', strtotime($review['created_at']));
        $has_reply    = !empty($review['vendor_reply']);
      ?>
      <div class="review-row">

        <div class="review-top">
          <div class="reviewer-info">
            <div class="avatar-circle">
              <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.4c-3.5 0-9 2.6-9 6.1V22h18v-1.5c0-3.5-5.5-6.1-9-6.1z"/></svg>
            </div>
            <div class="reviewer-text">
              <span class="reviewer-name"><?php echo htmlspecialchars($masked_name); ?></span>
              <?php if (!empty($review['order_id'])): ?>
                <span class="order-ref">Order ID <?php echo str_pad((string) $review['order_id'], 5, '0', STR_PAD_LEFT); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <span class="review-date"><?php echo $review_date; ?></span>
        </div>

        <div class="review-bottom">
          <span class="stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <span class="<?php echo $i <= $rating ? 'star-filled' : 'star-empty'; ?>">&#9733;</span>
            <?php endfor; ?>
          </span>
          <button type="button" class="btn btn-small" onclick="toggleReply(<?php echo $review['review_id']; ?>)">
            <?php echo $has_reply ? 'Edit' : 'Reply'; ?>
          </button>
        </div>

        <?php if (!empty($review['comment'])): ?>
          <p class="review-comment"><?php echo htmlspecialchars($review['comment']); ?></p>
        <?php endif; ?>

        <?php if ($has_reply): ?>
          <div class="vendor-reply-display">
            <span class="reply-label">
              Your reply<?php echo $review['replied_at'] ? ' &middot; ' . date('j M Y', strtotime($review['replied_at'])) : ''; ?>:
            </span>
            <p><?php echo htmlspecialchars($review['vendor_reply']); ?></p>
          </div>
        <?php endif; ?>

        <div class="reply-form-wrap" id="replyForm-<?php echo $review['review_id']; ?>">
          <form method="POST" action="review.php<?php echo $filter_qs; ?>">
            <input type="hidden" name="action" value="save_reply">
            <input type="hidden" name="review_id" value="<?php echo (int) $review['review_id']; ?>">
            <textarea name="reply_text" maxlength="1000"
                      placeholder="Write a reply to this review..."
                      oninput="document.getElementById('replyCount-<?php echo $review['review_id']; ?>').textContent = this.value.length;"
            ><?php echo htmlspecialchars($review['vendor_reply'] ?? ''); ?></textarea>
            <div class="char-count">
              <span id="replyCount-<?php echo $review['review_id']; ?>"><?php echo mb_strlen($review['vendor_reply'] ?? ''); ?></span>/1000
            </div>
            <div class="reply-form-actions">
              <button type="submit" class="btn">Submit Reply</button>
              <button type="button" class="btn-outline" style="border-radius:4px;padding:12px 28px;font-size:15px;cursor:pointer;"
                      onclick="toggleReply(<?php echo $review['review_id']; ?>)">Cancel</button>
            </div>
          </form>
        </div>

      </div>
    <?php endforeach; ?>

  </div>
</div>

<script>
  function toggleFilterPanel() {
    document.getElementById('filterPanel').classList.toggle('open');
  }

  function toggleReply(reviewId) {
    document.getElementById('replyForm-' + reviewId).classList.toggle('open');
  }
</script>

</body>
</html>
