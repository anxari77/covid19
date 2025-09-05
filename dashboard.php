<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_role = getUserRole();

// Redirect based on role
if ($user_role == 'patient') {
    header("Location: patient_dashboard.php");
    exit();
} elseif ($user_role == 'hospital') {
    header("Location: hospital_dashboard.php");
    exit();
} else {
    logout();
}
?>
