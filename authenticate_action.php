<?php
// Start session at VERY TOP (before any output)
session_start();

// Prevent any output before JSON response
ob_start();

// Set headers
header('Content-Type: application/json');

// Turn off error display (keep logging)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Verify session exists
if (!isset($_SESSION)) {
    die(json_encode(['success' => false, 'message' => 'Session initialization failed']));
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !isset($_SESSION['unit_name'])) {
    send_json_response(false, 'Not logged in or missing unit information');
}

// Database connection
try {
    $db = new mysqli("localhost", "root", "", "militaryinstituteprojects");
    if ($db->connect_error) {
        throw new Exception("Database connection failed: " . $db->connect_error);
    }
} catch (Exception $e) {
    send_json_response(false, 'Database connection error');
}

// Get POST data
$matricule = $_POST['matricule'] ?? '';
$password = $_POST['password'] ?? '';
$action = $_POST['action'] ?? '';
$project_id = $_POST['project_id'] ?? '';
$current_user_id = $_POST['current_user_id'] ?? '';

if (empty($matricule) || empty($password) || empty($action)) {
    send_json_response(false, 'Missing required fields');
}

try {
    // Handle authentication based on role
    switch ($_SESSION['role']) {
        case 'eleve':
            // Get student information
            $stmt = $db->prepare("
                SELECT e.*, u.unit_name 
                FROM Etudiant e
                JOIN Unit u ON e.unit_id = u.unit_id 
                WHERE e.matricule = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Database prepare error: " . $db->error);
            }
            
            $stmt->bind_param("s", $matricule);
            if (!$stmt->execute()) {
                throw new Exception("Database execute error: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $student = $result->fetch_assoc();

            if (!$student) {
                throw new Exception("No student found with matricule " . $matricule);
            }

            // Check if the student belongs to the correct unit
            if ($student['unit_id'] == $_SESSION['user_id'] || 
                ($_SESSION['role'] === 'eleve' && $student['unit_name'] === 'Académie Militaire')) {
                
                // Verify password using both methods
                $password_verified = false;
                
                // Try plaintext password first
                if ($password === $student['password_plaintext']) {
                    $password_verified = true;
                }
                // Try hashed password if plaintext fails
                else if (password_verify($password, $student['password_hash'])) {
                    $password_verified = true;
                }
                
                if ($password_verified) {
                    // Set action-specific session data
                    $_SESSION['action_user'] = [
                        'id' => $student['student_id'],
                        'matricule' => $student['matricule'],
                        'name' => $student['prenom'] . ' ' . $student['nom']
                    ];
                    $_SESSION['authenticated'] = true;
                    $_SESSION['auth_time'] = time();
                    
                    // Store authentication in session
                    $_SESSION['authenticated_student'] = $student['student_id'];
                    $_SESSION['student_matricule'] = $matricule;
                    
                    // Prepare response data
                    $response_data = [
                        'user' => [
                            'matricule' => $student['matricule'],
                            'nom' => $student['nom'],
                            'prenom' => $student['prenom']
                        ]
                    ];
                    
                    // Add redirect URL based on action
                    if ($action === 'reserve' && !empty($project_id)) {
                        $response_data['redirect'] = 'reserve_project.php?id=' . (int)$project_id;
                    } else if ($action === 'reservations') {
                        $response_data['redirect'] = 'my_reservations.php';
                    }
                    
                    send_json_response(true, 'Authentication successful', $response_data);
                } else {
                    throw new Exception("Invalid password for matricule " . $matricule);
                }
            } else {
                throw new Exception("Student does not belong to the correct unit");
            }
            break;

        case 'encadrant':
            // Get supervisor information
            $stmt = $db->prepare("
                SELECT e.*, u.unit_name 
                FROM Encadrant e
                JOIN Unit u ON e.unit_id = u.unit_id
                WHERE e.matricule = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Database prepare error: " . $db->error);
            }
            
            $stmt->bind_param("s", $matricule);
            if (!$stmt->execute()) {
                throw new Exception("Database execute error: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $encadrant = $result->fetch_assoc();

            if (!$encadrant) {
                throw new Exception("No supervisor found with matricule " . $matricule);
            }

            // Verify password using both methods
            $password_verified = false;
            
            // Try plaintext password first
            if ($password === $encadrant['password_plaintext']) {
                $password_verified = true;
            }
            // Try hashed password if plaintext fails
            else if (password_verify($password, $encadrant['password_hash'])) {
                $password_verified = true;
            }
            
            if ($password_verified) {
                // Set action-specific session data
                $_SESSION['action_user'] = [
                    'id' => $encadrant['encadrant_id'],
                    'matricule' => $encadrant['matricule'],
                    'name' => $encadrant['prenom'] . ' ' . $encadrant['nom']
                ];
                $_SESSION['authenticated'] = true;
                $_SESSION['auth_time'] = time();
                
                // Store authentication in session
                $_SESSION['authenticated_encadrant'] = $encadrant['encadrant_id'];
                $_SESSION['encadrant_matricule'] = $matricule;
                
                // Prepare response data
                $response_data = [
                    'user' => [
                        'matricule' => $encadrant['matricule'],
                        'nom' => $encadrant['nom'],
                        'prenom' => $encadrant['prenom']
                    ]
                ];
                
                // Add redirect URL based on action
                if ($action === 'create_project') {
                    $response_data['redirect'] = 'create_project.php';
                } else if ($action === 'my_projects') {
                    $response_data['redirect'] = 'my_projects.php';
                } else {
                    throw new Exception("Invalid action for supervisor: " . $action);
                }
                
                // Log the response data for debugging
                error_log("Supervisor authentication successful. Response data: " . print_r($response_data, true));
                
                send_json_response(true, 'Authentication successful', $response_data);
            } else {
                throw new Exception("Invalid password for matricule " . $matricule);
            }
            break;

        case 'admin':
            // Get admin information
            $stmt = $db->prepare("
                SELECT a.*, u.unit_name 
                FROM Administrateur a
                JOIN Unit u ON a.unit_id = u.unit_id
                WHERE a.matricule = ?
            ");
            
            if (!$stmt) {
                throw new Exception("Database prepare error: " . $db->error);
            }
            
            $stmt->bind_param("s", $matricule);
            if (!$stmt->execute()) {
                throw new Exception("Database execute error: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();

            if (!$admin) {
                throw new Exception("No administrator found with matricule " . $matricule);
            }

            // Verify password
            if (password_verify($password, $admin['password_hash'])) {
                $_SESSION['action_user'] = [
                    'id' => $admin['admin_id'],
                    'matricule' => $admin['matricule'],
                    'name' => $admin['prenom'] . ' ' . $admin['nom']
                ];
                $_SESSION['authenticated'] = true;
                $_SESSION['auth_time'] = time();
                
                // Set redirect based on action
                $redirect = 'admin_dashboard.php';
                if ($action === 'manage_projects') {
                    $redirect = 'manage_projects.php';
                }
                
                send_json_response(true, '', [
                    'redirect' => $redirect,
                    'debug' => [
                        'matricule' => $matricule,
                        'action' => $action,
                        'project_id' => $project_id
                    ]
                ]);
            } else {
                throw new Exception("Invalid password for matricule " . $matricule);
            }
            break;

        default:
            throw new Exception("Invalid role for authentication");
    }
} catch (Exception $e) {
    send_json_response(false, $e->getMessage());
}

$db->close();

// Function to send JSON response and exit
function send_json_response($success, $message = '', $data = []) {
    ob_end_clean(); // Clear ALL output buffers
    http_response_code(200);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}
?> 