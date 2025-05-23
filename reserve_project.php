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

// Get project details
$stmt = $db->prepare("
    SELECT p.*, e.nom as encadrant_nom, e.prenom as encadrant_prenom, e.grade as encadrant_grade
    FROM Projet p
    LEFT JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
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
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .detail-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .detail-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
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
            color: #27ae60;
        }
        
        .info-label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            margin-bottom: 1rem;
        }
        
        .form-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shield-lock"></i> Military Institute Projects
            </a>
            <div class="d-flex">
                <a href="student_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card detail-card mb-4">
                    <div class="card-body">
                        <h2 class="card-title mb-3"><?php echo htmlspecialchars($project['titre']); ?></h2>
                        
                        <div class="d-flex flex-wrap mb-4">
                            <span class="badge badge-team status-badge">
                                <i class="bi bi-people"></i> <?php echo $project['nombre_eleves'] > 1 ? 'Group Project' : 'Individual Project'; ?>
                            </span>
                            <span class="badge badge-available status-badge">
                                <i class="bi bi-check-circle"></i> Available
                            </span>
                        </div>

                        <div class="mb-4">
                            <h5 class="info-label">Description</h5>
                            <p class="info-value"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                        </div>

                        <div class="mb-3">
                            <h5 class="info-label">Supervisor</h5>
                            <p class="info-value">
                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($project['encadrant_grade'] . ' ' . $project['encadrant_prenom'] . ' ' . $project['encadrant_nom']); ?>
                            </p>
                        </div>
                    </div>
                </div>

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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>