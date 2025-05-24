<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'encadrant') {
    header("Location: login.php");
    exit();
}

// Verify POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_projects.php");
    exit();
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get project ID and verify ownership
$project_id = intval($_POST['project_id']);
$stmt = $db->prepare("SELECT encadrant_id, reservation_count FROM Projet WHERE project_id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();

// Verify project exists and belongs to this supervisor
if (!$project || $project['encadrant_id'] !== $_SESSION['user_id']) {
    $_SESSION['error'] = "Project not found or unauthorized access.";
    header("Location: my_projects.php");
    exit();
}

// Verify project is not reserved
if ($project['reservation_count'] > 0) {
    $_SESSION['error'] = "Cannot update a reserved project.";
    header("Location: my_projects.php");
    exit();
}

// Sanitize and validate input
$titre = trim($_POST['titre']);
$specialite = trim($_POST['specialite']);
$nombre_eleves = trim($_POST['nombre_eleves']);
$organisme = trim($_POST['organisme'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$description = trim($_POST['description']);
$objectif = trim($_POST['objectif']);
$resultats_attendus = trim($_POST['resultats_attendus']);

// Validate required fields
if (empty($titre) || empty($specialite) || empty($nombre_eleves) || 
    empty($description) || empty($objectif) || empty($resultats_attendus)) {
    $_SESSION['error'] = "All required fields must be filled.";
    header("Location: my_projects.php");
    exit();
}

// Validate specialty
if (!in_array($specialite, ['genie civil', 'telecomunication', 'electomecanique', 'genie informatique'])) {
    $_SESSION['error'] = "Invalid specialty.";
    header("Location: my_projects.php");
    exit();
}

// Validate number of students
if (!in_array($nombre_eleves, ['monome', 'binome'])) {
    $_SESSION['error'] = "Invalid number of students.";
    header("Location: my_projects.php");
    exit();
}

// Validate email if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email format.";
    header("Location: my_projects.php");
    exit();
}

// Validate phone if provided
if (!empty($phone) && !preg_match('/^[0-9+\s-]{8,20}$/', $phone)) {
    $_SESSION['error'] = "Invalid phone format.";
    header("Location: my_projects.php");
    exit();
}

// Update project
$stmt = $db->prepare("
    UPDATE Projet 
    SET titre = ?, 
        specialite = ?, 
        nombre_eleves = ?, 
        organisme = ?, 
        phone = ?, 
        email = ?, 
        address = ?, 
        description = ?, 
        objectif = ?, 
        resultats_attendus = ?,
        updated_at = CURRENT_TIMESTAMP
    WHERE project_id = ? AND encadrant_id = ?
");

$stmt->bind_param(
    "ssssssssssii",
    $titre,
    $specialite,
    $nombre_eleves,
    $organisme,
    $phone,
    $email,
    $address,
    $description,
    $objectif,
    $resultats_attendus,
    $project_id,
    $_SESSION['user_id']
);

if ($stmt->execute()) {
    $_SESSION['success'] = "Project updated successfully.";
} else {
    $_SESSION['error'] = "Error updating project: " . $stmt->error;
}

$stmt->close();
$db->close();

header("Location: my_projects.php");
exit();
?> 