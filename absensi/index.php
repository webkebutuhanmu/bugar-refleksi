<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $folder = ($_SESSION['role'] === 'supervisor') ? 'spv' : $_SESSION['role'];
    header("Location: $folder/dashboard_$folder.php");
    exit;
}
header("Location: login.php");
exit;