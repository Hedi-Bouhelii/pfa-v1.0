<?php
session_start();

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session and authentication flow
$debug_info = [
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'script_name' => $_SERVER['SCRIPT_NAME'],
    'referrer' => $_SERVER['HTTP_REFERER'] ?? 'none',
    'auth_checks' => []
];

// Check basic authentication
$debug_info['auth_checks']['basic'] = [
    'has_user_id' => isset($_SESSION['user_id']),
    'correct_role' => ($_SESSION['role'] ?? null) === 'encadrant',
    'has_unit' => isset($_SESSION['unit_name'])
];

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'encadrant' || !isset($_SESSION['unit_name'])) {
    $debug_info['redirect'] = 'login.php';
    error_log("MY_PROJECTS DEBUG: Basic auth failed - " . json_encode($debug_info));
    header("Location: login.php");
    exit();
}

// Check second authentication
$debug_info['auth_checks']['second_auth'] = [
    'has_auth_flag' => isset($_SESSION['authenticated_encadrant']),
    'auth_matches' => ($_SESSION['authenticated_encadrant'] ?? null) === ($_SESSION['user_id'] ?? null),
    'from_auth_action' => ($_SESSION['coming_from_auth'] ?? false)
];

if (!isset($_SESSION['authenticated_encadrant']) || $_SESSION['authenticated_encadrant'] !== $_SESSION['user_id']) {
    if (!isset($_SESSION['auth_attempted'])) {
        // First attempt - redirect to auth
        $_SESSION['auth_attempted'] = true;
        $_SESSION['auth_redirect_target'] = 'my_projects.php';
        error_log("MY_PROJECTS DEBUG: Redirecting to auth-action (first attempt) - " . json_encode($debug_info));
        header("Location: auth-action.php");
        exit();
    } else {
        // Already attempted auth - clear and redirect to dashboard
        unset($_SESSION['auth_attempted']);
        error_log("MY_PROJECTS DEBUG: Auth failed after attempt - redirecting to dashboard - " . json_encode($debug_info));
        header("Location: encadrant_dashboard.php");
        exit();
    }
}

// Successful auth - clear flags
unset($_SESSION['auth_attempted']);
unset($_SESSION['auth_redirect_target']);

// Debug output that will be visible in HTML source
echo "<!-- DEBUG: " . json_encode($debug_info, JSON_PRETTY_PRINT) . " -->";

// Database connection
error_log("MY_PROJECTS DEBUG: Connecting to database");
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    error_log("MY_PROJECTS DEBUG: Database connection failed - " . $db->connect_error);
    die("Connection failed: " . $db->connect_error);
}

// Get supervisor's projects
error_log("MY_PROJECTS DEBUG: Fetching projects for encadrant ID: " . $_SESSION['user_id']);
$stmt = $db->prepare("
    SELECT p.*, 
           COUNT(r.reservation_id) as reservation_count,
           GROUP_CONCAT(DISTINCT CONCAT(s1.prenom, ' ', s1.nom) SEPARATOR ', ') as students
    FROM Projet p
    LEFT JOIN Reservation r ON p.project_id = r.project_id
    LEFT JOIN Etudiant s1 ON r.student1_id = s1.student_id
    LEFT JOIN Etudiant s2 ON r.student2_id = s2.student_id
    WHERE p.encadrant_id = ?
    GROUP BY p.project_id
    ORDER BY p.created_at DESC
");

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$projects = $stmt->get_result();
error_log("MY_PROJECTS DEBUG: Found " . $projects->num_rows . " projects");

// Get supervisor information
error_log("MY_PROJECTS DEBUG: Fetching supervisor information");
$encadrant_stmt = $db->prepare("
    SELECT e.*, u.unit_name 
    FROM Encadrant e 
    LEFT JOIN Unit u ON e.unit_id = u.unit_id 
    WHERE e.encadrant_id = ?
");
$encadrant_stmt->bind_param("i", $_SESSION['user_id']);
$encadrant_stmt->execute();
$encadrant = $encadrant_stmt->get_result()->fetch_assoc();

if (!$encadrant) {
    error_log("MY_PROJECTS DEBUG: Supervisor not found for ID: " . $_SESSION['user_id']);
    session_unset();
    session_destroy();
    header("Location: login.php?error=invalid_session");
    exit();
}

error_log("MY_PROJECTS DEBUG: Successfully loaded supervisor: " . $encadrant['prenom'] . ' ' . $encadrant['nom']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Projects - Military Institute Projects</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
    
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
        
        .project-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 100%;
            background: white;
        }
        
        .project-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
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
        
        .badge-completed {
            background-color: rgba(108, 117, 125, 0.15);
            color: #6c757d;
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
        
        .btn-manage {
            background-color: var(--warning-color);
            color: white;
            border: none;
        }
        
        .btn-manage:hover {
            background-color: #e67e22;
        }
        
        .user-avatar-small {
            width: 40px;
            height: 40px;
            background-color: var(--secondary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--light-color);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: #7f8c8d;
            margin-bottom: 1.5rem;
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
                            <div class="fw-semibold"><?php echo htmlspecialchars($encadrant['grade'] . ' ' . $encadrant['prenom'] . ' ' . $encadrant['nom']); ?></div>
                            <div class="small text-light"><?php echo htmlspecialchars($_SESSION['unit_name']); ?></div>
                        </div>
                        <div class="user-avatar-small">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="encadrant_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="dashboard-container">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="section-title">
                    <i class="bi bi-journal-bookmark-fill"></i> My Projects
                </h4>
                <a href="encadrant_dashboard.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <div class="row">
                <?php if ($projects->num_rows > 0): ?>
                    <?php while($project = $projects->fetch_assoc()): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card project-card">
                                <div class="card-body">
                                    <span class="status-badge <?php 
                                        echo $project['reservation_count'] > 0 ? 'badge-reserved' : 'badge-available'; 
                                    ?>">
                                        <?php echo $project['reservation_count'] > 0 ? 'Reserved' : 'Available'; ?>
                                    </span>
                                    <h5 class="card-title"><?php echo htmlspecialchars($project['titre']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars(substr($project['description'], 0, 100)) . '...'; ?></p>
                                    
                                    <div class="project-meta">
                                        <strong>Status:</strong> <?php echo $project['reservation_count'] > 0 ? $project['reservation_count'] . ' reservations' : 'Available'; ?>
                                    </div>

                                    <?php if($project['students']): ?>
                                        <div class="project-meta mt-2">
                                            <strong>Students:</strong> <?php echo htmlspecialchars($project['students']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <a href="project_details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-details btn-action">
                                            <i class="bi bi-info-circle"></i> Details
                                        </a>
                                        <?php if($project['reservation_count'] > 0): ?>
                                            <a href="manage_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-manage btn-action">
                                                <i class="bi bi-people-fill"></i> Manage
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-action" onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($project)); ?>)">
                                                <i class="bi bi-pencil-square"></i> Update
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="bi bi-journal-x"></i>
                            <h3>No Projects Yet</h3>
                            <p>You haven't created any projects yet. Start by creating your first project.</p>
                            <a href="create_project.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Create New Project
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Update Project Modal -->
    <div class="modal fade" id="updateProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i> Update Project
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="updateProjectForm" method="POST" action="update_project.php">
                        <input type="hidden" name="project_id" id="update_project_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="update_titre" class="form-label required-field">Project Title</label>
                                <input type="text" class="form-control" id="update_titre" name="titre" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="update_specialite" class="form-label required-field">Specialty</label>
                                <select class="form-select" id="update_specialite" name="specialite" required>
                                    <option value="genie civil">Civil Engineering</option>
                                    <option value="telecomunication">Telecommunications</option>
                                    <option value="electomecanique">Electromechanical</option>
                                    <option value="genie informatique">Computer Engineering</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="update_nombre_eleves" class="form-label required-field">Number of Students</label>
                                <select class="form-select" id="update_nombre_eleves" name="nombre_eleves" required>
                                    <option value="monome">Individual</option>
                                    <option value="binome">Group (2 students)</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="update_organisme" class="form-label">Organization</label>
                                <input type="text" class="form-control" id="update_organisme" name="organisme">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="update_phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="update_phone" name="phone">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="update_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="update_email" name="email">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="update_address" class="form-label">Address</label>
                            <textarea class="form-control" id="update_address" name="address" rows="1"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="update_description" class="form-label required-field">Description</label>
                            <textarea class="form-control" id="update_description" name="description" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="update_objectif" class="form-label required-field">Objectives</label>
                            <textarea class="form-control" id="update_objectif" name="objectif" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="update_resultats_attendus" class="form-label required-field">Expected Results</label>
                            <textarea class="form-control" id="update_resultats_attendus" name="resultats_attendus" rows="3" required></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function openUpdateModal(project) {
            // Fill form fields with project data
            document.getElementById('update_project_id').value = project.project_id;
            document.getElementById('update_titre').value = project.titre;
            document.getElementById('update_specialite').value = project.specialite;
            document.getElementById('update_nombre_eleves').value = project.nombre_eleves;
            document.getElementById('update_organisme').value = project.organisme || '';
            document.getElementById('update_phone').value = project.phone || '';
            document.getElementById('update_email').value = project.email || '';
            document.getElementById('update_address').value = project.address || '';
            document.getElementById('update_description').value = project.description;
            document.getElementById('update_objectif').value = project.objectif;
            document.getElementById('update_resultats_attendus').value = project.resultats_attendus;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('updateProjectModal'));
            modal.show();
        }

        // Form validation
        document.getElementById('updateProjectForm').addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    </script>
</body>
</html> 