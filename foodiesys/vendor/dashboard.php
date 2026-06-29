<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$vendor_id = (int) $_SESSION['vendor_id'];

// ---------- Fetch vendor name + photo for the greeting ----------
$stmt = $conn->prepare("SELECT stall_name, profile_image FROM vendors WHERE vendor_id = ?");
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$vendor = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$stall_name    = ($vendor && !empty($vendor['stall_name'])) ? $vendor['stall_name'] : 'Vendor';
$profile_image = $vendor['profile_image'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendor Dashboard - Food Campus System</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background-color: #0a47a8;
    min-height: 100vh;
  }

  .page { max-width: 750px; margin: 0 auto; min-height: 100vh; }

  .logo-container { text-align: center; padding: 24px 0 4px; }
  .logo-container img { width: 120px; }

  .header {
    padding: 26px 28px 22px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
  }
  .header h1 { color: #ffffff; font-size: 26px; font-weight: 800; }
  .logout-link {
    color: #ffffff;
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
  }
  .logout-link:hover { text-decoration: underline; }

  .content-card {
    background-color: #d9d9d9;
    margin: 0 20px 30px;
    border-radius: 4px;
    padding: 30px 32px 50px;
  }

  /* ---- Profile row ---- */
  .profile-link {
    display: flex;
    align-items: center;
    gap: 24px;
    text-decoration: none;
    padding-bottom: 22px;
    margin-bottom: 30px;
    border-bottom: 1px solid #aaa;
  }

  .avatar-box {
    display: flex;
    border: 3px solid #0a47a8;
    border-radius: 4px;
    overflow: hidden;
    flex-shrink: 0;
    transition: transform 0.15s ease;
  }
  .profile-link:hover .avatar-box { transform: scale(1.03); }

  .avatar-icon {
    width: 64px;
    height: 76px;
    background-color: #bcbcbc;
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 1px solid #0a47a8;
  }
  .avatar-icon:last-child { border-right: none; }
  .avatar-icon svg { width: 36px; height: 36px; fill: #f0f0f0; }

  .avatar-photo {
    width: 64px;
    height: 76px;
    border-radius: 4px;
    object-fit: cover;
  }

  .profile-text {
    font-size: 28px;
    font-weight: 800;
    color: #0a47a8;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .profile-text svg { width: 26px; height: 26px; }

  /* ---- Stepper nav ---- */
  .stepper {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 56px 6px;
  }

  .stepper-line {
    position: absolute;
    top: 50%;
    left: 6%;
    right: 6%;
    height: 2px;
    background-color: #0a47a8;
    transform: translateY(-50%);
    z-index: 1;
  }

  .stepper-item {
    position: relative;
    z-index: 2;
    text-decoration: none;
  }

  .stepper-box {
    width: 54px;
    height: 54px;
    background-color: #0a47a8;
    border-radius: 4px;
    display: block;
    transition: background-color 0.15s ease, transform 0.15s ease;
  }
  .stepper-item:hover .stepper-box {
    background-color: #08368a;
    transform: scale(1.08);
  }

  .stepper-label {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    white-space: nowrap;
    font-weight: 800;
    color: #0a47a8;
    font-size: 14px;
    text-decoration: underline;
  }
  .stepper-item.label-top .stepper-label { bottom: 72px; }
  .stepper-item.label-bottom .stepper-label { top: 72px; }

  @media (max-width: 480px) {
    .content-card { margin: 0 12px 20px; padding: 24px 16px 60px; }
    .profile-text { font-size: 22px; }
    .avatar-icon { width: 50px; height: 60px; }
    .stepper-box { width: 42px; height: 42px; }
    .stepper-label { font-size: 12px; }
  }
</style>
</head>
<body>

<div class="logo-container">
  <img src="assets/images/logo.png" alt="FoodieSys">
</div>

<div class="page">
  <div class="header">
    <h1>Welcome, <?php echo htmlspecialchars($stall_name); ?></h1>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>

  <div class="content-card">

    <a href="profile.php" class="profile-link">
      <div class="avatar-box">
        <?php if (!empty($profile_image)): ?>
          <div class="avatar-icon">
            <img class="avatar-photo" src="<?php echo htmlspecialchars($profile_image); ?>" alt="Stall profile picture">
          </div>
        <?php else: ?>
          <div class="avatar-icon">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.4c-3.5 0-9 2.6-9 6.1V22h18v-1.5c0-3.5-5.5-6.1-9-6.1z"/></svg>
          </div>
          <div class="avatar-icon">
            <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12zm0 2.4c-3.5 0-9 2.6-9 6.1V22h18v-1.5c0-3.5-5.5-6.1-9-6.1z"/></svg>
          </div>
        <?php endif; ?>
      </div>
      <div class="profile-text">
        Profile
        <svg viewBox="0 0 24 24" fill="#0a47a8"><path d="M12 2l2.2 5.6L20 8l-4.5 3.9L16.9 18 12 14.8 7.1 18l1.4-6.1L4 8l5.8-.4L12 2z"/></svg>
      </div>
    </a>

    <div class="stepper">
      <div class="stepper-line"></div>

      <a href="order_queue.php" class="stepper-item label-bottom">
        <span class="stepper-label">Order Queue</span>
        <span class="stepper-box"></span>
      </a>

      <a href="order_history.php" class="stepper-item label-top">
        <span class="stepper-label">Order History</span>
        <span class="stepper-box"></span>
      </a>

      <a href="review.php" class="stepper-item label-bottom">
        <span class="stepper-label">Review</span>
        <span class="stepper-box"></span>
      </a>

      <a href="analytics.php" class="stepper-item label-top">
        <span class="stepper-label">Analytics</span>
        <span class="stepper-box"></span>
      </a>
    </div>

  </div>
</div>

</body>
</html>
