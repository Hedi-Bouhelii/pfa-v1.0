<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// Check if project ID is provided
if (!isset($_GET['id'])) {
    header("Location: student_dashboard.php");
    exit();
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

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
    WHERE p.project_id = ?
");

$stmt->bind_param("i", $_GET['id']);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();

if (!$project) {
    header("Location: student_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details - Military Institute</title>
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
        
        .badge-reserved {
            background-color: rgba(52, 152, 219, 0.15);
            color: var(--secondary-color);
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
        
        .student-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .student-info:last-child {
            margin-bottom: 0;
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
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--secondary-color);
            border: 2px solid white;
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -1rem;
            top: 1rem;
            width: 2px;
            height: calc(100% - 1rem);
            background: #e9ecef;
        }
        
        .timeline-item:last-child::after {
            display: none;
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
                            <?php if ($project['is_available']): ?>
                                <span class="badge badge-available status-badge">
                                    <i class="bi bi-check-circle"></i> Available
                                </span>
                            <?php else: ?>
                                <span class="badge badge-reserved status-badge">
                                    <i class="bi bi-bookmark-check"></i> Reserved
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="info-section">
                            <h5 class="info-label"><i class="bi bi-file-text"></i> Description</h5>
                            <p class="info-value"><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
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

                        <?php if (!$project['is_available']): ?>
                        <div class="info-section">
                            <h5 class="info-label"><i class="bi bi-calendar-check"></i> Reservation Details</h5>
                            <div class="info-value">
                                <?php if ($project['student1_nom']): ?>
                                    <div class="student-info">
                                        <strong>Primary Student:</strong>
                                        <div><i class="bi bi-person"></i> <?php echo htmlspecialchars($project['student1_prenom'] . ' ' . $project['student1_nom']); ?></div>
                                        <div><i class="bi bi-card-text"></i> <?php echo htmlspecialchars($project['student1_matricule']); ?></div>
                                        <div><i class="bi bi-mortarboard"></i> <?php echo htmlspecialchars($project['student1_class']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($project['student2_nom']): ?>
                                    <div class="student-info">
                                        <strong>Partner Student:</strong>
                                        <div><i class="bi bi-person"></i> <?php echo htmlspecialchars($project['student2_prenom'] . ' ' . $project['student2_nom']); ?></div>
                                        <div><i class="bi bi-card-text"></i> <?php echo htmlspecialchars($project['student2_matricule']); ?></div>
                                        <div><i class="bi bi-mortarboard"></i> <?php echo htmlspecialchars($project['student2_class']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <div class="timeline mt-4">
                                    <div class="timeline-item">
                                        <strong>Reserved on:</strong>
                                        <div><?php echo date('F j, Y H:i', strtotime($project['reservation_date'])); ?></div>
                                    </div>
                                    
                                    <?php if ($project['is_approved'] !== null): ?>
                                        <div class="timeline-item">
                                            <strong>Status:</strong>
                                            <div>
                                                <?php if ($project['is_approved']): ?>
                                                    <span class="badge badge-approved">
                                                        <i class="bi bi-check-circle"></i> Approved
                                                    </span>
                                                    <div class="mt-2">
                                                        <small>Approved on: <?php echo date('F j, Y H:i', strtotime($project['approval_date'])); ?></small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge badge-rejected">
                                                        <i class="bi bi-x-circle"></i> Rejected
                                                    </span>
                                                    <?php if ($project['rejection_reason']): ?>
                                                        <div class="mt-2">
                                                            <small>Reason: <?php echo htmlspecialchars($project['rejection_reason']); ?></small>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="student_dashboard.php" class="btn btn-outline-secondary btn-action">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                    <?php if ($_SESSION['role'] === 'eleve' && $project['is_available']): ?>
                        <a href="reserve_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-primary btn-action">
                            <i class="bi bi-bookmark-plus"></i> Reserve Project
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>