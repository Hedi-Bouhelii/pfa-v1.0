<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['authenticated_admin'])) {
    header("Location: login.php");
    exit();
}

// Verify POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php");
    exit();
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get reservation ID and verify it exists and is pending
$reservation_id = intval($_POST['reservation_id']);
$stmt = $db->prepare("
    SELECT r.*, p.project_id
    FROM Reservation r
    JOIN Projet p ON r.project_id = p.project_id
    WHERE r.reservation_id = ? AND r.status = 'pending'
");

$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$result = $stmt->get_result();
$reservation = $result->fetch_assoc();

// Verify reservation exists and is pending
if (!$reservation) {
    $_SESSION['error'] = "Reservation not found or already processed.";
    header("Location: admin_dashboard.php");
    exit();
}

// Update reservation status
$stmt = $db->prepare("UPDATE Reservation SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE reservation_id = ?");
$stmt->bind_param("i", $reservation_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Reservation rejected successfully.";
} else {
    $_SESSION['error'] = "Error rejecting reservation: " . $stmt->error;
}

$stmt->close();
$db->close();

header("Location: admin_dashboard.php");
exit(); 