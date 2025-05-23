<?php
session_start();

// Debug information
$debug_info = [
    'session' => $_SESSION,
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'role' => $_SESSION['role'] ?? 'not set',
    'unit_name' => $_SESSION['unit_name'] ?? 'not set'
];

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eleve' || !isset($_SESSION['unit_name'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get student information (only for personal actions)
$student_info = $db->query("
    SELECT e.*, c.class_name as classe_nom, u.unit_name 
    FROM Etudiant e 
    LEFT JOIN Classe c ON e.class_id = c.class_id 
    LEFT JOIN Unit u ON e.unit_id = u.unit_id 
    WHERE e.student_id = " . intval($_SESSION['user_id'])
);

// Debug output
echo "<!-- Debug Information: " . json_encode($debug_info) . " -->";
echo "<!-- Student ID from session: " . $_SESSION['user_id'] . " -->";

if (!$student_info) {
    die("Error in student query: " . $db->error);
}

$student_info = $student_info->fetch_assoc();
if (!$student_info) {
    // If student not found, clear session and redirect to login
    session_unset();
    session_destroy();
    header("Location: login.php?error=invalid_session");
    exit();
}

// Get all projects (both available and reserved)
$projects_query = "
    SELECT p.*, e.nom as encadrant_nom, e.prenom as encadrant_prenom, e.grade as encadrant_grade,
           r.reservation_id, r.is_approved,
           CASE 
               WHEN r.student1_id = " . intval($_SESSION['user_id']) . " OR r.student2_id = " . intval($_SESSION['user_id']) . " THEN 'reserved'
               WHEN p.is_available = TRUE THEN 'available'
               ELSE 'unavailable'
           END as project_status
    FROM Projet p
    LEFT JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
    LEFT JOIN Reservation r ON p.project_id = r.project_id
    ORDER BY p.created_at DESC";

$projects = $db->query($projects_query);

if (!$projects) {
    die("Error in projects query: " . $db->error);
}

// Get class projects (reserved by classmates)
$class_projects_query = "
    SELECT p.*, e.nom as encadrant_nom, e.prenom as encadrant_prenom, e.grade as encadrant_grade,
           r.reservation_id, r.is_approved, r.reservation_date,
           e1.prenom as student1_prenom, e1.nom as student1_nom,
           e2.prenom as student2_prenom, e2.nom as student2_nom
    FROM Projet p
    LEFT JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
    LEFT JOIN Reservation r ON p.project_id = r.project_id
    LEFT JOIN Etudiant e1 ON r.student1_id = e1.student_id
    LEFT JOIN Etudiant e2 ON r.student2_id = e2.student_id
    WHERE (e1.class_id = " . intval($student_info['class_id']) . " OR e2.class_id = " . intval($student_info['class_id']) . ")
    AND (e1.student_id != " . intval($_SESSION['user_id']) . " AND e2.student_id != " . intval($_SESSION['user_id']) . ")
    ORDER BY r.reservation_date DESC";

$class_projects = $db->query($class_projects_query);

if (!$class_projects) {
    die("Error in class projects query: " . $db->error);
}

// Get all encadrants for filter
$encadrants_query = "SELECT encadrant_id, grade, prenom, nom FROM Encadrant ORDER BY nom, prenom";
$encadrants = $db->query($encadrants_query);

if (!$encadrants) {
    die("Error in encadrants query: " . $db->error);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Military Institute Projects</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/choices.js@9.0.1/public/assets/styles/choices.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 0;
        }
        
        .navbar-brand {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .navbar-brand i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .dashboard-container {
            padding: 2rem 0;
        }
        
        .sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            height: 100%;
        }
        
        .sidebar-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 2px solid var(--light-color);
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .sidebar-title i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .quick-action-item {
            border: none;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            padding: 1rem;
            background-color: var(--light-color);
        }
        
        .quick-action-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            background-color: var(--secondary-color);
            color: white;
        }
        
        .quick-action-item:hover .action-icon,
        .quick-action-item:hover .action-text {
            color: white !important;
        }
        
        .action-icon {
            font-size: 1.25rem;
            color: var(--secondary-color);
            margin-right: 10px;
        }
        
        .action-text {
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .action-description {
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .project-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
        }
        
        .project-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }
        
        .project-card .card-body {
            padding: 1.5rem;
        }
        
        .project-card .card-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.75rem;
        }
        
        .project-card .card-text {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .project-card .card-footer {
            background: transparent;
            border-top: 1px solid #eee;
            padding: 1rem 1.5rem;
        }
        
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-available {
            background-color: rgba(39, 174, 96, 0.15);
            color: var(--success-color);
        }
        
        .badge-reserved {
            background-color: rgba(52, 152, 219, 0.15);
            color: var(--secondary-color);
        }
        
        .badge-unavailable {
            background-color: rgba(231, 76, 60, 0.15);
            color: var(--danger-color);
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
        
        .project-meta {
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 0.5rem;
        }
        
        .project-meta strong {
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .btn-action {
            border-radius: 8px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .btn-details {
            background-color: var(--light-color);
            color: var(--dark-color);
            border: none;
        }
        
        .btn-details:hover {
            background-color: #d5dbdb;
        }
        
        .btn-reserve {
            background-color: var(--secondary-color);
            color: white;
            border: none;
        }
        
        .btn-reserve:hover {
            background-color: #2980b9;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .user-info h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--primary-color);
        }
        
        .user-info p {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-bottom: 0;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stats-icon {
            font-size: 1.75rem;
            color: var(--secondary-color);
            margin-bottom: 0.75rem;
        }
        
        .stats-number {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }
        
        .stats-label {
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        /* Custom modal styling */
        .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            border-bottom: none;
            padding: 1.5rem;
        }
        
        .modal-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-modal {
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-modal-primary {
            background-color: var(--secondary-color);
            border: none;
        }
        
        .btn-modal-primary:hover {
            background-color: #2980b9;
        }
        
        /* Custom select styling */
        .choices__inner {
            border-radius: 8px !important;
            padding: 0.75rem 1rem !important;
            border: 1px solid #ddd !important;
            min-height: auto !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                margin-bottom: 2rem;
            }
        }
        
        /* Animation classes */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shield-alt"></i> Military Institute Projects
            </a>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="me-3 text-end d-none d-sm-block">
                            <div class="fw-semibold" id="userName">Welcome</div>
                            <div class="small text-light" id="userMatricule">Student</div>
                        </div>
                        <div class="user-avatar-small" id="userAvatar">
                            <i class="fas fa-user"></i>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Dashboard Content -->
    <div class="dashboard-container">
        <div class="container">
            <div class="row">
                <!-- Sidebar Column -->
                <div class="col-lg-4">
                    <div class="sidebar animate__animated animate__fadeInLeft">
                        <!-- User Profile -->
                        <div class="user-profile">
                            <div class="user-avatar">
                                ST
                            </div>
                            <p>Welcome Student</p>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="row">
                            <?php
                            // Get available projects count
                            $available_query = "
                                SELECT COUNT(*) as count 
                                FROM Projet p
                                WHERE p.is_available = 1
                                AND NOT EXISTS (
                                    SELECT 1 FROM Reservation r 
                                    WHERE r.project_id = p.project_id 
                                    AND r.is_approved = 1
                                )";
                            $available_result = $db->query($available_query);
                            $available_count = $available_result->fetch_assoc()['count'];

                            // Get reserved projects count
                            $reserved_query = "
                                SELECT COUNT(*) as count 
                                FROM Reservation r 
                                WHERE (r.student1_id = " . intval($_SESSION['user_id']) . " OR r.student2_id = " . intval($_SESSION['user_id']) . ")
                                AND r.is_approved IS NULL";
                            $reserved_result = $db->query($reserved_query);
                            $reserved_count = $reserved_result->fetch_assoc()['count'];
                            ?>
                            <div class="col-md-6">
                                <div class="stats-card">
                                    <div class="stats-icon text-success">
                                        <i class="bi bi-folder-check"></i>
                                    </div>
                                    <div class="stats-number"><?php echo $available_count; ?></div>
                                    <div class="stats-label">Available Projects</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stats-card">
                                    <div class="stats-icon text-primary">
                                        <i class="bi bi-hourglass-split"></i>
                                    </div>
                                    <div class="stats-number"><?php echo $reserved_count; ?></div>
                                    <div class="stats-label">Reserved Projects</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <h5 class="sidebar-title">
                            <i class="bi bi-lightning-charge-fill"></i> Quick Actions
                        </h5>
                        <div class="list-group list-group-flush">
                            <a href="#" class="list-group-item list-group-item-action quick-action-item" data-bs-toggle="modal" data-bs-target="#authModal" data-action="reservations">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-bookmark-check action-icon"></i>
                                    <div>
                                        <div class="action-text">My Reservations</div>
                                        <small class="action-description">View and manage your project reservations</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Main Content Column -->
                <div class="col-lg-8">
                    <!-- Filter Section -->
                    <div class="filter-section animate__animated animate__fadeInRight">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="statusFilter" class="form-label">Status</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="all">All Projects</option>
                                    <option value="available">Available</option>
                                    <option value="reserved">My Reservations</option>
                                    <option value="unavailable">Unavailable</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="encadrantFilter" class="form-label">Supervisor</label>
                                <select class="form-select" id="encadrantFilter">
                                    <option value="all">All Supervisors</option>
                                    <?php while($encadrant = $encadrants->fetch_assoc()): ?>
                                        <option value="<?php echo $encadrant['encadrant_id']; ?>">
                                            <?php echo htmlspecialchars($encadrant['grade'] . ' ' . $encadrant['prenom'] . ' ' . $encadrant['nom']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="searchProject" class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="searchProject" placeholder="Search projects...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Projects Section -->
                    <div class="mb-5 animate__animated animate__fadeInUp">
                        <h5 class="section-title">
                            <i class="bi bi-journal-bookmark-fill"></i> Projects
                        </h5>
                        <div class="row g-4" id="projectsContainer">
                            <?php while($project = $projects->fetch_assoc()): ?>
                                <div class="col-md-6 project-item" 
                                    data-status="<?php echo $project['project_status']; ?>" 
                                    data-encadrant="<?php echo $project['encadrant_id']; ?>" 
                                    data-title="<?php echo htmlspecialchars($project['titre']); ?>" 
                                    data-description="<?php echo htmlspecialchars($project['description']); ?>">
                                    <div class="card project-card h-100">
                                        <div class="card-body">
                                            <span class="status-badge badge-<?php echo $project['project_status']; ?>">
                                                <?php 
                                                    echo ucfirst($project['project_status']);
                                                    if ($project['is_approved'] === 1) {
                                                        echo ' (Approved)';
                                                    } elseif ($project['is_approved'] === 0) {
                                                        echo ' (Rejected)';
                                                    }
                                                ?>
                                            </span>
                                            <h5 class="card-title"><?php echo htmlspecialchars($project['titre']); ?></h5>
                                            <p class="card-text"><?php echo htmlspecialchars(substr($project['description'], 0, 150)) . '...'; ?></p>
                                            <div class="project-meta">
                                                <strong>Type:</strong> <?php echo htmlspecialchars($project['nombre_eleves']); ?><br>
                                                <strong>Supervisor:</strong> <?php echo htmlspecialchars($project['encadrant_grade'] . ' ' . $project['encadrant_prenom'] . ' ' . $project['encadrant_nom']); ?><br>
                                                <strong>Created:</strong> <?php echo date('d/m/Y', strtotime($project['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="d-flex justify-content-between">
                                                <a href="project_details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-details btn-action">
                                                    <i class="bi bi-info-circle"></i> Details
                                                </a>
                                                <?php if ($project['project_status'] === 'available'): ?>
                                                    <a href="#" class="btn btn-reserve btn-action" data-bs-toggle="modal" data-bs-target="#authModal" data-action="reserve" data-project-id="<?php echo $project['project_id']; ?>">
                                                        <i class="bi bi-bookmark-plus"></i> Reserve
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Authentication Modal -->
    <div class="modal fade" id="authModal" tabindex="-1" aria-labelledby="authModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="authModalLabel">
                        <i class="bi bi-shield-lock me-2"></i> Military Authentication Required
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="authForm">
                        <input type="hidden" id="action" name="action">
                        <input type="hidden" id="projectId" name="project_id">
                        
                        <div class="mb-4 text-center">
                            <i class="bi bi-person-badge-fill" style="font-size: 3rem; color: var(--secondary-color);"></i>
                            <p class="mt-2">Please authenticate with your military credentials to proceed</p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="matricule" class="form-label">Military ID Number</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person-vcard"></i>
                                </span>
                                <input type="text" class="form-control" id="matricule" name="matricule" placeholder="MID-XXXX-XXX" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <a href="#" class="text-decoration-none">Forgot password?</a>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="authSubmit">
                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                Authenticate
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> By authenticating, you agree to the Military Institute's security policies.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/choices.js@9.0.1/public/assets/scripts/choices.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize enhanced select elements
            const statusFilter = new Choices('#statusFilter', {
                searchEnabled: false,
                itemSelectText: '',
                classNames: {
                    containerInner: 'choices__inner',
                    input: 'choices__input',
                }
            });
            
            const encadrantFilter = new Choices('#encadrantFilter', {
                searchEnabled: true,
                itemSelectText: '',
                classNames: {
                    containerInner: 'choices__inner',
                    input: 'choices__input',
                }
            });
            
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
            });
            
            // Project filtering functionality
            function filterProjects() {
                const status = document.getElementById('statusFilter').value;
                const encadrant = document.getElementById('encadrantFilter').value;
                const search = document.getElementById('searchProject').value.toLowerCase();
                
                document.querySelectorAll('.project-item').forEach(project => {
                    const projectStatus = project.dataset.status;
                    const projectEncadrant = project.dataset.encadrant;
                    const projectTitle = project.dataset.title.toLowerCase();
                    const projectDescription = project.dataset.description.toLowerCase();
                    
                    const statusMatch = status === 'all' || projectStatus === status;
                    const encadrantMatch = encadrant === 'all' || projectEncadrant === encadrant;
                    const searchMatch = !search || 
                                      projectTitle.includes(search) || 
                                      projectDescription.includes(search);
                    
                    project.style.display = statusMatch && encadrantMatch && searchMatch ? 'block' : 'none';
                });
            }
            
            // Event listeners for filters
            document.getElementById('statusFilter').addEventListener('change', filterProjects);
            document.getElementById('encadrantFilter').addEventListener('change', filterProjects);
            document.getElementById('searchProject').addEventListener('input', filterProjects);
            
            // Auth modal handling
            const authModal = document.getElementById('authModal');
            const authForm = document.getElementById('authForm');
            const actionInput = document.getElementById('action');
            const projectIdInput = document.getElementById('projectId');
            const authSubmit = document.getElementById('authSubmit');

            authModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const action = button.getAttribute('data-action');
                const projectId = button.getAttribute('data-project-id');
                
                actionInput.value = action;
                if (projectId) {
                    projectIdInput.value = projectId;
                }
                
                // Update modal title based on action
                let modalTitle = 'Authentication Required';
                if (action === 'reserve') {
                    modalTitle = 'Reserve Project - Authentication';
                } else if (action === 'reservations') {
                    modalTitle = 'View My Reservations - Authentication';
                }
                
                document.getElementById('authModalLabel').innerHTML = `
                    <i class="bi bi-shield-lock me-2"></i> ${modalTitle}
                `;
            });
            
            // Form submission handling
            authForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = authSubmit;
                const spinner = submitBtn.querySelector('.spinner-border');
                submitBtn.disabled = true;
                spinner.classList.remove('d-none');

                try {
                    const formData = new FormData(authForm);
                    const response = await fetch('authenticate_action.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Update UI without waiting for modal
                        if (data.data.user) {
                            document.getElementById('userName').textContent = 
                                data.data.user.prenom + ' ' + data.data.user.nom;
                        }

                        // Show success toast
                        showToast('Authentication successful! Redirecting...', 'success');

                        // Get base URL
                        const baseUrl = window.location.origin + '/prjt_v1.0/';
                        const redirectUrl = baseUrl + data.data.redirect;

                        // Close modal immediately
                        const modal = bootstrap.Modal.getInstance(authModal);
                        if (modal) {
                            modal.hide();
                        }

                        // Force redirect with absolute URL
                        window.location.replace(redirectUrl);
                        
                        // Backup redirect after 500ms if replace fails
                        setTimeout(() => {
                            window.location.href = redirectUrl;
                        }, 500);
                    } else {
                        showToast(data.message || 'Authentication failed', 'danger');
                    }
                    
                } catch (error) {
                    showToast('Authentication failed: ' + error.message, 'danger');
                } finally {
                    submitBtn.disabled = false;
                    spinner.classList.add('d-none');
                }
            });
            
            // Enhanced toast notification system
            function showToast(message, type = 'info', duration = 5000) {
                // Create container if it doesn't exist
                let toastContainer = document.getElementById('toastContainer');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.id = 'toastContainer';
                    toastContainer.style.position = 'fixed';
                    toastContainer.style.top = '20px';
                    toastContainer.style.right = '20px';
                    toastContainer.style.zIndex = '1100';
                    document.body.appendChild(toastContainer);
                }
                
                // Create toast
                const toastId = 'toast-' + Date.now();
                const toast = document.createElement('div');
                toast.id = toastId;
                toast.className = `toast show align-items-center text-white bg-${type} border-0`;
                toast.style.marginBottom = '10px';
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                
                // Toast content
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body d-flex align-items-center">
                            <i class="bi ${
                                type === 'success' ? 'bi-check-circle-fill' : 
                                type === 'danger' ? 'bi-exclamation-triangle-fill' : 
                                'bi-info-circle-fill'
                            } me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                
                toastContainer.appendChild(toast);
                
                // Initialize Bootstrap toast
                const bsToast = new bootstrap.Toast(toast, {
                    autohide: true,
                    delay: duration
                });
                bsToast.show();
                
                // Auto-remove after animation
                toast.addEventListener('hidden.bs.toast', () => {
                    toast.remove();
                });
                
                // Manual close handler
                toast.querySelector('[data-bs-dismiss="toast"]').addEventListener('click', () => {
                    bsToast.hide();
                });
            }
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover focus'
                });
            });
            
            // Initialize popovers
            const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl, {
                    trigger: 'focus'
                });
            });
            
            // Intersection Observer for animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.project-card').forEach(card => {
                observer.observe(card);
            });
            
            // Search functionality
            const searchInput = document.getElementById('searchProject');
            const searchButton = searchInput.nextElementSibling;
            
            searchButton.addEventListener('click', filterProjects);
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    filterProjects();
                }
            });
            
            // Initial filter on page load
            filterProjects();
        });
    </script>
</body>
</html>