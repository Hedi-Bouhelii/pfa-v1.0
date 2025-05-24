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

// Get project ID and verify it exists and has no reservations
$project_id = intval($_POST['project_id']);
$stmt = $db->prepare("
    SELECT p.*, COUNT(r.reservation_id) as reservation_count
    FROM Projet p
    LEFT JOIN Reservation r ON p.project_id = r.project_id
    WHERE p.project_id = ?
    GROUP BY p.project_id
");

$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();

// Verify project exists and has no reservations
if (!$project) {
    $_SESSION['error'] = "Project not found.";
    header("Location: admin_dashboard.php");
    exit();
}

if ($project['reservation_count'] > 0) {
    $_SESSION['error'] = "Cannot delete a project that has reservations.";
    header("Location: admin_dashboard.php");
    exit();
}

// Delete project
$stmt = $db->prepare("DELETE FROM Projet WHERE project_id = ?");
$stmt->bind_param("i", $project_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Project deleted successfully.";
} else {
    $_SESSION['error'] = "Error deleting project: " . $stmt->error;
}

$stmt->close();
$db->close();

header("Location: admin_dashboard.php");
exit(); 