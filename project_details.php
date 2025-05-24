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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a2a4a;
            --secondary-color: #3a6ea5;
            --accent-color: #e67e22;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #f8f9fa;
            --dark-color: #121a26;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --card-shadow-hover: 0 8px 24px rgba(0, 0, 0, 0.12);
            --border-radius: 10px;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.7;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 0.75rem 0;
        }
        
        .navbar-brand {
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            letter-spacing: 0.5px;
            font-size: 1.25rem;
        }
        
        .project-hero {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            padding: 3rem 2.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }
        
        .project-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
        }
        
        .project-hero::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: 0;
            width: 100%;
            height: 60px;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="%23ffffff"></path><path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="%23ffffff"></path><path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="%23ffffff"></path></svg>');
            background-size: cover;
            background-repeat: no-repeat;
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.75rem;
            margin-bottom: 2.5rem;
        }
        
        .card-item {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 2rem;
            transition: all 0.3s ease;
            border-top: 4px solid var(--secondary-color);
            position: relative;
            overflow: hidden;
        }
        
        .card-item:hover {
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-5px);
        }
        
        .card-item.wide {
            grid-column: span 2;
        }
        
        .card-item.tall {
            grid-row: span 2;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.75rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }
        
        .card-header i {
            font-size: 1.75rem;
            margin-right: 1.25rem;
            color: var(--secondary-color);
            background: rgba(58, 110, 165, 0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1.35rem;
            color: var(--primary-color);
        }
        
        .status-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.75rem;
        }
        
        .status-pill {
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .pill-team {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .pill-available {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }
        
        .pill-reserved {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 1.25rem;
            align-items: flex-start;
        }
        
        .detail-row i {
            color: var(--secondary-color);
            margin-right: 1.25rem;
            margin-top: 0.25rem;
            flex-shrink: 0;
            font-size: 1.1rem;
        }
        
        .detail-content {
            flex: 1;
        }
        
        .detail-content strong {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .detail-content p {
            margin-bottom: 0;
        }
        
        .student-card {
            background: rgba(236, 240, 241, 0.5);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            border-left: 4px solid var(--secondary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }
        
        .student-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .student-card h6 {
            color: var(--primary-color);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.1rem;
        }
        
        .student-card h6 i {
            color: var(--accent-color);
        }
        
        .timeline {
            position: relative;
            padding-left: 2.5rem;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 1.75rem;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.75rem;
            top: 0;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            background: var(--secondary-color);
            border: 4px solid white;
            box-shadow: 0 0 0 2px var(--secondary-color);
            z-index: 2;
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -1.1rem;
            top: 1.25rem;
            width: 2px;
            height: calc(100% - 1.25rem);
            background: #e9ecef;
            z-index: 1;
        }
        
        .timeline-item:last-child::after {
            display: none;
        }
        
        .btn-action {
            border-radius: 8px;
            padding: 0.75rem 1.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: 0.5px;
            font-family: 'Oswald', sans-serif;
            text-transform: uppercase;
            font-size: 0.9rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-action i {
            font-size: 1.1rem;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-primary:hover {
            background-color: #2d5988;
            border-color: #2d5988;
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(58, 110, 165, 0.2);
        }
        
        .btn-outline-secondary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 6px 12px rgba(26, 42, 74, 0.2);
        }
        
        .badge-specialty {
            background-color: rgba(58, 110, 165, 0.1);
            color: var(--secondary-color);
            padding: 0.5rem 0.75rem;
            border-radius: 50px;
            font-weight: 500;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .badge-specialty i {
            font-size: 0.9rem;
        }
        
        .requirement-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding: 0.75rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .requirement-item:hover {
            background-color: rgba(236, 240, 241, 0.5);
        }
        
        .requirement-item i {
            color: var(--success-color);
            margin-right: 1rem;
            margin-top: 0.2rem;
            flex-shrink: 0;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 2rem;
            padding-bottom: 0.75rem;
            color: var(--primary-color);
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary-color), var(--accent-color));
            border-radius: 2px;
        }
        
        @media (max-width: 992px) {
            .card-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
            
            .card-item.wide,
            .card-item.tall {
                grid-column: span 1;
                grid-row: span 1;
            }
            
            .project-hero {
                padding: 2rem 1.5rem;
            }
            
            .project-hero::before {
                display: none;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shield-lock me-2"></i> Military Institute Projects
            </a>
            <div class="d-flex">
                <a href="student_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-arrow-left me-1"></i> Dashboard
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                <!-- Project Hero Section -->
                <div class="project-hero">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h1 class="mb-3">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <?php echo htmlspecialchars($project['titre']); ?>
                            </h1>
                            <div class="status-pills">
                                <span class="status-pill pill-team">
                                    <i class="bi bi-people-fill"></i>
                                    <?php echo $project['nombre_eleves'] > 1 ? 'Group Project' : 'Individual Project'; ?>
                                </span>
                                <?php if ($project['is_available']): ?>
                                    <span class="status-pill pill-available">
                                        <i class="bi bi-check-circle-fill"></i> Available
                                    </span>
                                <?php else: ?>
                                    <span class="status-pill pill-reserved">
                                        <i class="bi bi-bookmark-check-fill"></i> Reserved
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex">
                            <?php if ($_SESSION['role'] === 'eleve' && $project['is_available']): ?>
                                <a href="reserve_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-light btn-action">
                                    <i class="bi bi-bookmark-plus"></i> Reserve Project
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Main Content Section -->
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Description Card -->
                        <div class="card-item mb-4">
                            <div class="card-header">
                                <i class="bi bi-file-text"></i>
                                <h3>Project Description</h3>
                            </div>
                            <div class="detail-row">
                                <i class="bi bi-card-text"></i>
                                <div class="detail-content">
                                    <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                                </div>
                            </div>
                            
                            <h4 class="section-title mt-4">Objectives</h4>
                            <div class="detail-row">
                                <i class="bi bi-bullseye"></i>
                                <div class="detail-content">
                                    <p>Develop a comprehensive military-grade application for cadet training management with real-time analytics and reporting capabilities.</p>
                                </div>
                            </div>
                            
                            <h4 class="section-title mt-4">Expected Outcomes</h4>
                            <div class="detail-row">
                                <i class="bi bi-clipboard2-check"></i>
                                <div class="detail-content">
                                    <ul class="ps-3">
                                        <li>Functional web application with secure authentication</li>
                                        <li>Database of cadet performance metrics</li>
                                        <li>Analytics dashboard for instructors</li>
                                        <li>Technical documentation and user manual</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!$project['is_available']): ?>
                        <!-- Timeline Card -->
                        <div class="card-item mb-4">
                            <div class="card-header">
                                <i class="bi bi-clock-history"></i>
                                <h3>Project Timeline</h3>
                            </div>
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="detail-row">
                                        <i class="bi bi-calendar-event"></i>
                                        <div class="detail-content">
                                            <strong>Reserved on:</strong>
                                            <div><?php echo date('F j, Y H:i', strtotime($project['reservation_date'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($project['is_approved'] !== null): ?>
                                    <div class="timeline-item">
                                        <div class="detail-row">
                                            <i class="bi bi-clipboard2-check"></i>
                                            <div class="detail-content">
                                                <strong>Status:</strong>
                                                <div class="mt-2">
                                                    <?php if ($project['is_approved']): ?>
                                                        <span class="status-pill pill-available">
                                                            <i class="bi bi-check-circle-fill"></i> Approved
                                                        </span>
                                                        <div class="mt-2">
                                                            <small><i class="bi bi-calendar2-check me-1"></i>Approved on: <?php echo date('F j, Y H:i', strtotime($project['approval_date'])); ?></small>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="status-pill" style="background-color: rgba(231, 76, 60, 0.1); color: var(--danger-color);">
                                                            <i class="bi bi-x-circle-fill"></i> Rejected
                                                        </span>
                                                        <?php if ($project['rejection_reason']): ?>
                                                            <div class="mt-2">
                                                                <small><i class="bi bi-exclamation-triangle me-1"></i>Reason: <?php echo htmlspecialchars($project['rejection_reason']); ?></small>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="timeline-item">
                                    <div class="detail-row">
                                        <i class="bi bi-calendar-range"></i>
                                        <div class="detail-content">
                                            <strong>Project Milestones</strong>
                                            <div class="mt-3">
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="badge bg-success rounded-circle me-2" style="width: 10px; height: 10px;"></div>
                                                    <span>Initial Proposal - <?php echo date('M j', strtotime('+1 week')); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="badge bg-warning rounded-circle me-2" style="width: 10px; height: 10px;"></div>
                                                    <span>Prototype Review - <?php echo date('M j', strtotime('+4 weeks')); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="badge bg-secondary rounded-circle me-2" style="width: 10px; height: 10px;"></div>
                                                    <span>Final Implementation - <?php echo date('M j', strtotime('+10 weeks')); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <div class="badge bg-primary rounded-circle me-2" style="width: 10px; height: 10px;"></div>
                                                    <span>Project Presentation - <?php echo date('M j', strtotime('+12 weeks')); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Supervisor Card -->
                        <div class="card-item mb-4">
                            <div class="card-header">
                                <i class="bi bi-person-badge"></i>
                                <h3>Supervisor</h3>
                            </div>
                            <div class="text-center mb-3">
                                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px; border: 3px solid var(--secondary-color);">
                                    <i class="bi bi-person-fill" style="font-size: 2.5rem; color: var(--secondary-color);"></i>
                                </div>
                            </div>
                            <div class="detail-row">
                                <i class="bi bi-person-vcard"></i>
                                <div class="detail-content">
                                    <strong><?php echo htmlspecialchars($project['encadrant_grade'] . ' ' . $project['encadrant_prenom'] . ' ' . $project['encadrant_nom']); ?></strong>
                                    <div class="text-muted small">Project Supervisor</div>
                                </div>
                            </div>
                            <?php if ($project['encadrant_email']): ?>
                            <div class="detail-row">
                                <i class="bi bi-envelope"></i>
                                <div class="detail-content">
                                    <a href="mailto:<?php echo htmlspecialchars($project['encadrant_email']); ?>">
                                        <?php echo htmlspecialchars($project['encadrant_email']); ?>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($project['encadrant_phone']): ?>
                            <div class="detail-row">
                                <i class="bi bi-telephone"></i>
                                <div class="detail-content">
                                    <a href="tel:<?php echo htmlspecialchars($project['encadrant_phone']); ?>">
                                        <?php echo htmlspecialchars($project['encadrant_phone']); ?>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <i class="bi bi-building"></i>
                                <div class="detail-content">
                                    <strong>Department:</strong> Computer Science & Engineering
                                </div>
                            </div>
                            <div class="detail-row">
                                <i class="bi bi-clock"></i>
                                <div class="detail-content">
                                    <strong>Office Hours:</strong> Mon-Fri, 9:00-17:00
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Details Card -->
                        <div class="card-item mb-4">
                            <div class="card-header">
                                <i class="bi bi-info-circle"></i>
                                <h3>Project Details</h3>
                            </div>
                            <div class="detail-row">
                                <i class="bi bi-calendar"></i>
                                <div class="detail-content">
                                    <strong>Created:</strong> <?php echo date('F j, Y', strtotime($project['created_at'])); ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <i class="bi bi-people"></i>
                                <div class="detail-content">
                                    <strong>Team Size:</strong> <?php echo $project['nombre_eleves'] > 1 ? 'Group ('.$project['nombre_eleves'].' students)' : 'Individual'; ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <i class="bi bi-clock-history"></i>
                                <div class="detail-content">
                                    <strong>Duration:</strong> 12 weeks
                                </div>
                            </div>
                            <div class="detail-row">
                                <i class="bi bi-tags"></i>
                                <div class="detail-content">
                                    <strong>Specialties:</strong>
                                    <div class="mt-2">
                                        <span class="badge-specialty"><i class="bi bi-code-slash"></i> Computer Science</span>
                                        <span class="badge-specialty"><i class="bi bi-cpu"></i> Engineering</span>
                                        <span class="badge-specialty"><i class="bi bi-shield-lock"></i> Security</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Requirements Card -->
                        <div class="card-item mb-4">
                            <div class="card-header">
                                <i class="bi bi-list-check"></i>
                                <h3>Requirements</h3>
                            </div>
                            <div class="requirement-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <div>
                                    <strong>Technical Skills:</strong>
                                    <div class="mt-1">
                                        <span class="badge bg-light text-dark me-1">Python</span>
                                        <span class="badge bg-light text-dark me-1">JavaScript</span>
                                        <span class="badge bg-light text-dark me-1">SQL</span>
                                        <span class="badge bg-light text-dark">Django</span>
                                    </div>
                                </div>
                            </div>
                            <div class="requirement-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <div>
                                    <strong>Duration:</strong> 12 weeks (full-time)
                                </div>
                            </div>
                            <div class="requirement-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <div>
                                    <strong>Security Clearance:</strong> Level 2 required
                                </div>
                            </div>
                            <div class="requirement-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <div>
                                    <strong>Deliverables:</strong>
                                    <div class="mt-1">
                                        <span class="badge bg-light text-dark me-1">Final report</span>
                                        <span class="badge bg-light text-dark me-1">Presentation</span>
                                        <span class="badge bg-light text-dark">Source code</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!$project['is_available']): ?>
                        <!-- Students Card -->
                        <div class="card-item">
                            <div class="card-header">
                                <i class="bi bi-mortarboard"></i>
                                <h3>Assigned Students</h3>
                            </div>
                            
                            <?php if ($project['student1_nom']): ?>
                                <div class="student-card">
                                    <h6><i class="bi bi-person-fill"></i> Primary Student</h6>
                                    <div class="detail-row">
                                        <i class="bi bi-person"></i>
                                        <div class="detail-content">
                                            <?php echo htmlspecialchars($project['student1_prenom'] . ' ' . $project['student1_nom']); ?>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <i class="bi bi-123"></i>
                                        <div class="detail-content">
                                            <?php echo htmlspecialchars($project['student1_matricule']); ?>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <i class="bi bi-mortarboard"></i>
                                        <div class="detail-content">
                                            <?php echo htmlspecialchars($project['student1_class']); ?>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <i class="bi bi-envelope"></i>
                                        <div class="detail-content">
                                            student1@military.edu
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($project['student2_nom']): ?>
                                <div class="student-card">
                                    <h6><i class="bi bi-person-fill"></i> Partner Student</h6>
                                    <div class="detail-row">
                                        <i class="bi bi-person"></i>
                                        <div class="detail-content">
                                            <?php echo htmlspecialchars($project['student2_prenom'] . ' ' . $project['student2_nom']); ?>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <i class="bi bi-123"></i>
                                        <div class="detail-content">
                                            <?php echo htmlspecialchars($project['student2_matricule']); ?>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <i class="bi bi-mortarboard"></i>
                                        <div class="detail-content">
                                            <?php echo htmlspecialchars($project['student2_class']); ?>
                                        </div>
                                    </div>
                                    <div class="detail-row">
                                        <i class="bi bi-envelope"></i>
                                        <div class="detail-content">
                                            student2@military.edu
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="#" class="btn btn-outline-secondary btn-action">
                        <i class="bi bi-download"></i> Download Project Brief
                    </a>
                    <a href="student_dashboard.php" class="btn btn-outline-secondary btn-action">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-shield-lock me-2"></i> Military Institute</h5>
                    <p class="text-muted">Advanced research and development projects for military applications.</p>
                </div>
                <div class="col-md-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-decoration-none text-muted">Projects</a></li>
                        <li><a href="#" class="text-decoration-none text-muted">Supervisors</a></li>
                        <li><a href="#" class="text-decoration-none text-muted">Resources</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Contact</h5>
                    <ul class="list-unstyled text-muted">
                        <li><i class="bi bi-envelope me-2"></i> projects@military.edu</li>
                        <li><i class="bi bi-telephone me-2"></i> +1 (555) 123-4567</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-secondary">
            <div class="text-center text-muted">
                <small>&copy; 2023 Military Institute. All rights reserved.</small>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>