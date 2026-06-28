<?php
session_start();

// Clear all session data
$_SESSION = [];
session_unset();
session_destroy();

// Send the vendor back to the login page
header("Location: login.php");
exit;
