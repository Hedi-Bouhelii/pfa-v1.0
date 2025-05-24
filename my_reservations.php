<?php
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eleve') {
    header("Location: login.php");
    exit();
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get student details
$student_stmt = $db->prepare("
    SELECT e.*, c.class_name 
    FROM Etudiant e
    LEFT JOIN Classe c ON e.class_id = c.class_id
    WHERE e.student_id = ?
");
$student_stmt->bind_param("i", $_SESSION['user_id']);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

if (!$student) {
    // If student not found, clear session and redirect to login
    session_unset();
    session_destroy();
    header("Location: login.php?error=invalid_session");
    exit();
}

// Get student's reservations
// First, verify the student exists in the Etudiant table
$student_check = $db->query("SELECT student_id FROM Etudiant WHERE student_id = " . intval($_SESSION['user_id']));
if ($student_check->num_rows === 0) {
    die("Student ID {$_SESSION['user_id']} not found in Etudiant table");
}

// Check total reservations in database
$all_reservations = $db->query("SELECT COUNT(*) as total FROM Reservation");
$total_reservations = $all_reservations->fetch_assoc()['total'];

// Check reservations for this student
$student_reservations = $db->query("
    SELECT COUNT(*) as total 
    FROM Reservation 
    WHERE student1_id = " . intval($_SESSION['user_id']) . " 
    OR student2_id = " . intval($_SESSION['user_id'])
);
$student_res_count = $student_reservations->fetch_assoc()['total'];

// Try a simpler query first for debugging
$simple_query = "
    SELECT r.*, p.titre, p.description
    FROM Reservation r
    JOIN Projet p ON r.project_id = p.project_id
    WHERE r.student1_id = " . intval($_SESSION['user_id']) . " 
    OR r.student2_id = " . intval($_SESSION['user_id']);

$simple_result = $db->query($simple_query);
$debug_info['simple_query_result'] = $simple_result->fetch_all(MYSQLI_ASSOC);

// If simple query works, proceed with full query
if (count($debug_info['simple_query_result']) > 0) {
    $reservations_query = "
        SELECT r.*, 
               p.titre, p.description, p.nombre_eleves,
               e.grade as encadrant_grade, e.prenom as encadrant_prenom, e.nom as encadrant_nom,
               e1.prenom as student1_prenom, e1.nom as student1_nom, e1.matricule as student1_matricule,
               e2.prenom as student2_prenom, e2.nom as student2_nom, e2.matricule as student2_matricule
        FROM Reservation r
        JOIN Projet p ON r.project_id = p.project_id
        LEFT JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
        LEFT JOIN Etudiant e1 ON r.student1_id = e1.student_id
        LEFT JOIN Etudiant e2 ON r.student2_id = e2.student_id
        WHERE r.student1_id = ? OR r.student2_id = ?
        ORDER BY r.reservation_date DESC";

    $reservations_stmt = $db->prepare($reservations_query);
    $reservations_stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $reservations_stmt->execute();
    $reservations = $reservations_stmt->get_result();
} else {
    $reservations = $simple_result; // Use the simple query result if full query fails
}

// Add error checking
if (!$reservations) {
    die("Query failed: " . $db->error);
}

// Debug output
$debug_info = [
    'user_id' => $_SESSION['user_id'],
    'role' => $_SESSION['role'],
    'reservations_count' => $reservations->num_rows,
    'student_info' => $student,
    'total_reservations_in_db' => $total_reservations,
    'reservations_for_this_student' => $student_res_count,
    'simple_query_result' => $debug_info['simple_query_result'],
    'db_error' => $db->error,
    'session_data' => [
        'authenticated_student' => $_SESSION['authenticated_student'] ?? null,
        'student_matricule' => $_SESSION['student_matricule'] ?? null,
        'action_user' => $_SESSION['action_user'] ?? null
    ]
];

echo "<div class='container alert alert-warning mt-3'>Debug Information: <pre>" . print_r($debug_info, true) . "</pre></div>";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - Military Institute</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: var(--dark-color);
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-menu .dropdown-menu {
            min-width: 200px;
            padding: 0.5rem 0;
            margin-top: 0.5rem;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        
        .user-menu .dropdown-item {
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            color: var(--dark-color);
        }
        
        .user-menu .dropdown-item i {
            margin-right: 0.75rem;
            color: var(--secondary-color);
        }
        
        .user-menu .dropdown-item:hover {
            background-color: var(--light-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            color: white;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .user-info:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.2rem;
        }
        
        .user-details {
            line-height: 1.2;
        }
        
        .user-name {
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .user-class {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .reservation-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .reservation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .status-badge {
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            margin-bottom: 1rem;
        }
        
        .badge-pending {
            background-color: rgba(243, 156, 18, 0.15);
            color: var(--warning-color);
        }
        
        .badge-approved {
            background-color: rgba(39, 174, 96, 0.15);
            color: var(--success-color);
        }
        
        .badge-rejected {
            background-color: rgba(231, 76, 60, 0.15);
            color: var(--danger-color);
        }
        
        .info-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .info-label i {
            margin-right: 0.5rem;
            color: var(--secondary-color);
        }
        
        .info-value {
            color: #555;
            margin-bottom: 1.5rem;
            padding-left: 1.75rem;
        }
        
        .btn-action {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shield-lock"></i> Military Institute Projects
            </a>
            <div class="d-flex align-items-center">
                <div class="user-menu dropdown">
                    <div class="user-info" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?></div>
                            <div class="user-class"><?php echo htmlspecialchars($student['class_name']); ?></div>
                        </div>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="student_dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h2 class="mb-4">My Project Reservations</h2>
                
                <?php if ($reservations->num_rows > 0): ?>
                    <?php while($reservation = $reservations->fetch_assoc()): ?>
                        <div class="card reservation-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h3 class="card-title h4 mb-0"><?php echo htmlspecialchars($reservation['titre']); ?></h3>
                                    <span class="status-badge badge-<?php 
                                        echo $reservation['is_approved'] === null ? 'pending' : 
                                            ($reservation['is_approved'] ? 'approved' : 'rejected'); 
                                    ?>">
                                        <?php 
                                        echo $reservation['is_approved'] === null ? 'Pending Approval' : 
                                            ($reservation['is_approved'] ? 'Approved' : 'Rejected'); 
                                        ?>
                                    </span>
                                </div>

                                <div class="info-section">
                                    <h5 class="info-label"><i class="bi bi-file-text"></i> Description</h5>
                                    <p class="info-value"><?php echo nl2br(htmlspecialchars($reservation['description'])); ?></p>
                                </div>

                                <div class="info-section">
                                    <h5 class="info-label"><i class="bi bi-person-badge"></i> Supervisor</h5>
                                    <div class="info-value">
                                        <strong><?php echo htmlspecialchars($reservation['encadrant_grade'] . ' ' . $reservation['encadrant_prenom'] . ' ' . $reservation['encadrant_nom']); ?></strong>
                                    </div>
                                </div>

                                <div class="info-section">
                                    <h5 class="info-label"><i class="bi bi-people"></i> Team Members</h5>
                                    <div class="info-value">
                                        <div class="mb-2">
                                            <strong>Primary Student:</strong><br>
                                            <?php echo htmlspecialchars($reservation['student1_prenom'] . ' ' . $reservation['student1_nom'] . ' (' . $reservation['student1_matricule'] . ')'); ?>
                                        </div>
                                        <?php if ($reservation['student2_id']): ?>
                                            <div>
                                                <strong>Partner:</strong><br>
                                                <?php echo htmlspecialchars($reservation['student2_prenom'] . ' ' . $reservation['student2_nom'] . ' (' . $reservation['student2_matricule'] . ')'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="info-section">
                                    <h5 class="info-label"><i class="bi bi-calendar"></i> Reservation Details</h5>
                                    <div class="info-value">
                                        <div><strong>Reserved on:</strong> <?php echo date('F j, Y', strtotime($reservation['reservation_date'])); ?></div>
                                        <?php if ($reservation['is_approved'] !== null): ?>
                                            <div><strong>Status updated on:</strong> <?php echo date('F j, Y', strtotime($reservation['approval_date'])); ?></div>
                                        <?php endif; ?>
                                        <?php if ($reservation['rejection_reason']): ?>
                                            <div class="mt-2">
                                                <strong>Rejection Reason:</strong><br>
                                                <?php echo nl2br(htmlspecialchars($reservation['rejection_reason'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> You haven't made any project reservations yet.
                    </div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="student_dashboard.php" class="btn btn-primary btn-action">
                        <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>