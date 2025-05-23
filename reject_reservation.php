<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$project_id = $data['project_id'] ?? null;
$reason = $data['reason'] ?? null;

if (!$project_id || !$reason) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Project ID and rejection reason are required']);
    exit();
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if project has a pending reservation
$stmt = $db->prepare("
    SELECT r.* 
    FROM Reservation r
    WHERE r.project_id = ? AND r.is_approved IS NULL
");

$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$reservation = $result->fetch_assoc();

if (!$reservation) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No pending reservation found for this project']);
    exit();
}

// Start transaction
$db->begin_transaction();

try {
    // Update reservation status
    $stmt = $db->prepare("
        UPDATE Reservation 
        SET is_approved = FALSE, 
            rejection_reason = ?,
            rejection_date = NOW(),
            rejected_by = ?
        WHERE project_id = ? AND is_approved IS NULL
    ");

    $stmt->bind_param("sii", $reason, $_SESSION['user_id'], $project_id);
    $stmt->execute();

    // Make project available again
    $stmt = $db->prepare("UPDATE Projet SET is_available = TRUE WHERE project_id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();

    // Get notification data
    $stmt = $db->prepare("
        SELECT 
            p.encadrant_id,
            e.email as encadrant_email,
            s1.email as student1_email,
            s2.email as student2_email
        FROM Projet p
        JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
        LEFT JOIN Reservation r ON p.project_id = r.project_id
        LEFT JOIN Etudiant s1 ON r.student1_id = s1.student_id
        LEFT JOIN Etudiant s2 ON r.student2_id = s2.student_id
        WHERE p.project_id = ?
    ");
    
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notification_data = $result->fetch_assoc();

    // TODO: Implement email notification system
    // For now, we'll just commit the transaction

    $db->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Project reservation rejected successfully']);
} catch (Exception $e) {
    $db->rollback();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to reject project reservation: ' . $e->getMessage()]);
} 