<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eleve') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Check if reservation_id is provided
if (!isset($_POST['reservation_id'])) {
    die(json_encode(['success' => false, 'message' => 'Reservation ID is required']));
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$reservation_id = intval($_POST['reservation_id']);
$student_id = intval($_SESSION['user_id']);

// Start transaction
$db->begin_transaction();

try {
    // Check if the reservation belongs to the student
    $stmt = $db->prepare("
        SELECT r.*, p.project_id 
        FROM Reservation r
        JOIN Projet p ON r.project_id = p.project_id
        WHERE r.reservation_id = ? 
        AND (r.student1_id = ? OR r.student2_id = ?)
        AND r.is_approved IS NULL
    ");
    
    $stmt->bind_param("iii", $reservation_id, $student_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Reservation not found or cannot be cancelled');
    }
    
    $reservation = $result->fetch_assoc();
    
    // Delete the reservation
    $stmt = $db->prepare("DELETE FROM Reservation WHERE reservation_id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    
    // Update project availability
    $stmt = $db->prepare("UPDATE Projet SET is_available = TRUE WHERE project_id = ?");
    $stmt->bind_param("i", $reservation['project_id']);
    $stmt->execute();
    
    // Commit transaction
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Reservation cancelled successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$db->close();
?> 