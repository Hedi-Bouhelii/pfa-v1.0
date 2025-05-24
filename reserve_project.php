<?php
session_start();

// Check authentication
if (!isset($_SESSION['authenticated'])) {
    header("Location: student_dashboard.php");
    exit();
}

// Get project ID
$project_id = $_GET['id'] ?? $_POST['project_id'] ?? null;
$project_id = intval($project_id);

if (!$project_id) {
    $_SESSION['error'] = "No project specified";
    header("Location: student_dashboard.php");
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
$student_stmt->bind_param("i", $_SESSION['action_user']['id']);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

// Get project details with enhanced information
$stmt = $db->prepare("
    SELECT p.*, 
           e.nom as encadrant_nom, e.prenom as encadrant_prenom, e.grade as encadrant_grade,
           e.email as encadrant_email, e.phone as encadrant_phone,
           s1.nom as student1_nom, s1.prenom as student1_prenom, s1.matricule as student1_matricule,
           s2.nom as student2_nom, s2.prenom as student2_prenom, s2.matricule as student2_matricule,
           c1.class_name as student1_class, c2.class_name as student2_class,
           r.reservation_date, r.is_approved, r.approval_date, r.rejection_reason
    FROM Projet p
    LEFT JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
    LEFT JOIN Reservation r ON p.project_id = r.project_id
    LEFT JOIN Etudiant s1 ON r.student1_id = s1.student_id
    LEFT JOIN Etudiant s2 ON r.student2_id = s2.student_id
    LEFT JOIN Classe c1 ON s1.class_id = c1.class_id
    LEFT JOIN Classe c2 ON s2.class_id = c2.class_id
    WHERE p.project_id = ? AND p.is_available = 1
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    $_SESSION['error'] = "Project not found or not available";
    header("Location: student_dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student1_id = $_SESSION['action_user']['id'];
    $student2_id = !empty($_POST['student2_id']) ? intval($_POST['student2_id']) : null;
    $reservation_date = date('Y-m-d H:i:s');
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // 1. Create reservation
        $insert_stmt = $db->prepare("
            INSERT INTO Reservation (project_id, student1_id, student2_id, reservation_date)
            VALUES (?, ?, ?, ?)
        ");
        $insert_stmt->bind_param("iiis", $project_id, $student1_id, $student2_id, $reservation_date);
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Failed to create reservation: " . $db->error);
        }
        
        // 2. Update project availability
        $update_stmt = $db->prepare("UPDATE Projet SET is_available = 0 WHERE project_id = ?");
        $update_stmt->bind_param("i", $project_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Failed to update project status: " . $db->error);
        }
        
        // Commit transaction if both queries succeeded
        $db->commit();
        
        $_SESSION['success'] = "Project reserved successfully!";
        header("Location: student_dashboard.php");
        exit();
        
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['error'] = "Reservation failed: " . $e->getMessage();
        header("Location: student_dashboard.php");
        exit();
    }
}

// Get available students for partner selection
$students_stmt = $db->prepare("
    SELECT student_id, matricule, prenom, nom 
    FROM Etudiant 
    WHERE student_id != ? 
    AND class_id = (SELECT class_id FROM Etudiant WHERE student_id = ?)
    AND student_id NOT IN (
        SELECT student1_id FROM Reservation WHERE is_approved IS NULL
        UNION
        SELECT student2_id FROM Reservation WHERE is_approved IS NULL
    )
    ORDER BY nom, prenom
");
$students_stmt->bind_param("ii", $_SESSION['action_user']['id'], $_SESSION['action_user']['id']);
$students_stmt->execute();
$available_students = $students_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve Project - Military Institute</title>
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
        
        .detail-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .detail-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .status-badge {
            font-size: 0.9rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .badge-specialty {
            background-color: rgba(52, 152, 219, 0.15);
            color: var(--secondary-color);
        }
        
        .badge-team {
            background-color: rgba(108, 117, 125, 0.15);
            color: #6c757d;
        }
        
        .badge-available {
            background-color: rgba(39, 174, 96, 0.15);
            color: var(--success-color);
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
        
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
                <!-- Project Details Card -->
                <div class="card detail-card">
                    <div class="card-header">
                        <h2 class="mb-0"><?php echo htmlspecialchars($project['titre']); ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap mb-4">
                            <span class="badge badge-team status-badge">
                                <i class="bi bi-people"></i> <?php echo $project['nombre_eleves'] > 1 ? 'Group Project' : 'Individual Project'; ?>
                            </span>
                            <span class="badge badge-available status-badge">
                                <i class="bi bi-check-circle"></i> Available
                            </span>
                        </div>

                        <div class="info-section">
                            <h5 class="info-label"><i class="bi bi-file-text"></i> Description</h5>
                            <p class="info-value"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                        </div>

                        <div class="info-section">
                            <h5 class="info-label"><i class="bi bi-bullseye"></i> Objectives</h5>
                            <p class="info-value"><?php echo nl2br(htmlspecialchars($project['objectif'])); ?></p>
                        </div>

                        <div class="info-section">
                            <h5 class="info-label"><i class="bi bi-graph-up"></i> Expected Results</h5>
                            <p class="info-value"><?php echo nl2br(htmlspecialchars($project['resultats_attendus'])); ?></p>
                        </div>

                        <div class="info-section">
                            <h5 class="info-label"><i class="bi bi-person-badge"></i> Supervisor</h5>
                            <div class="info-value">
                                <div class="student-info">
                                    <strong><?php echo htmlspecialchars($project['encadrant_grade'] . ' ' . $project['encadrant_prenom'] . ' ' . $project['encadrant_nom']); ?></strong>
                                    <?php if ($project['encadrant_email']): ?>
                                        <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($project['encadrant_email']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($project['encadrant_phone']): ?>
                                        <div><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($project['encadrant_phone']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($project['organisme'] || $project['address']): ?>
                        <div class="info-section">
                            <h5 class="info-label"><i class="bi bi-building"></i> Organization Details</h5>
                            <div class="info-value">
                                <?php if ($project['organisme']): ?>
                                    <div><i class="bi bi-building"></i> <?php echo htmlspecialchars($project['organisme']); ?></div>
                                <?php endif; ?>
                                <?php if ($project['address']): ?>
                                    <div><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($project['address']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-section">
                            <h3 class="mb-4"><i class="bi bi-bookmark-check"></i> Reservation Form</h3>
                            <form method="POST">
                                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                
                                <?php if ($project['nombre_eleves'] > 1): ?>
                                <div class="mb-4">
                                    <label class="form-label info-label">Project Partner</label>
                                    <select class="form-select" name="student2_id">
                                        <option value="">No partner</option>
                                        <?php while($student = $available_students->fetch_assoc()): ?>
                                            <option value="<?php echo $student['student_id']; ?>">
                                                <?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom'] . ' (' . $student['matricule'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <small class="text-muted">Required for group projects</small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-check-circle me-2"></i> Confirm Reservation
                                    </button>
                                    <a href="student_dashboard.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>