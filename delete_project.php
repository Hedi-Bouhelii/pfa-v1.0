<?php
session_start();

// Check if user is logged in and is a supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'encadrant') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$project_id = $data['project_id'] ?? null;

if (!$project_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit();
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if project exists and belongs to this supervisor
$stmt = $db->prepare("
    SELECT p.*, COUNT(r.reservation_id) as reservation_count
    FROM Projet p
    LEFT JOIN Reservation r ON p.project_id = r.project_id
    WHERE p.project_id = ? AND p.encadrant_id = ?
    GROUP BY p.project_id
");

$stmt->bind_param("ii", $project_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();

if (!$project) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Project not found or unauthorized']);
    exit();
}

// Check if project has any reservations
if ($project['reservation_count'] > 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Cannot delete a project that has reservations']);
    exit();
}

// Start transaction
$db->begin_transaction();

try {
    // Delete project
    $stmt = $db->prepare("DELETE FROM Projet WHERE project_id = ?");
    $stmt->bind_param("i", $project_id);
    
    if ($stmt->execute()) {
        $db->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
    } else {
        throw new Exception('Failed to delete project');
    }
} catch (Exception $e) {
    $db->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to delete project: ' . $e->getMessage()]);
} 