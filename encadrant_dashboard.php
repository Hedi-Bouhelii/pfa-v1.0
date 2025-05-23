<?php
session_start();

// Check if user is logged in and is an encadrant
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'encadrant' || !isset($_SESSION['unit_name'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get encadrant information (only for personal actions)
$encadrant_info = $db->query("
    SELECT e.*, u.unit_name 
    FROM Encadrant e 
    LEFT JOIN Unit u ON e.unit_id = u.unit_id 
    WHERE e.encadrant_id = " . intval($_SESSION['user_id'])
);

if (!$encadrant_info) {
    die("Error in encadrant query: " . $db->error);
}

$encadrant_info = $encadrant_info->fetch_assoc();
if (!$encadrant_info) {
    die("Error: Encadrant information not found for ID: " . $_SESSION['user_id']);
}

// Get all encadrants from the same unit
$unit_encadrants = $db->query("
    SELECT e.*, u.unit_name 
    FROM Encadrant e 
    JOIN Unit u ON e.unit_id = u.unit_id 
    WHERE u.unit_name = '" . $db->real_escape_string($_SESSION['unit_name']) . "'
    ORDER BY e.grade, e.nom, e.prenom
");

if (!$unit_encadrants) {
    die("Error in unit encadrants query: " . $db->error);
}

// Get all projects from the unit
$unit_projects = $db->query("
    SELECT p.*, e.nom as encadrant_nom, e.prenom as encadrant_prenom, e.grade as encadrant_grade,
           COUNT(r.reservation_id) as reservation_count,
           GROUP_CONCAT(DISTINCT CONCAT(s1.prenom, ' ', s1.nom) SEPARATOR ', ') as students
    FROM Projet p
    JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
    JOIN Unit u ON e.unit_id = u.unit_id
    LEFT JOIN Reservation r ON p.project_id = r.project_id
    LEFT JOIN Etudiant s1 ON r.student1_id = s1.student_id
    LEFT JOIN Etudiant s2 ON r.student2_id = s2.student_id
    WHERE u.unit_name = '" . $db->real_escape_string($_SESSION['unit_name']) . "'
    GROUP BY p.project_id
    ORDER BY p.created_at DESC
");

if (!$unit_projects) {
    die("Error in unit projects query: " . $db->error);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard - Military Institute Projects</title>
    
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
        
        .action-card {
            border: none;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            padding: 1rem;
            background-color: var(--light-color);
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            background-color: var(--secondary-color);
            color: white;
        }
        
        .action-card:hover .action-icon,
        .action-card:hover .action-text {
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
        
        .supervisor-card {
            border-left: 4px solid var(--secondary-color);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .supervisor-card:hover {
            transform: translateX(5px);
        }
        
        .project-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 100%;
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
                            <div class="fw-semibold"><?php echo htmlspecialchars($encadrant_info['grade'] . ' ' . $encadrant_info['prenom'] . ' ' . $encadrant_info['nom']); ?></div>
                            <div class="small text-light"><?php echo htmlspecialchars($encadrant_info['specialite']); ?></div>
                        </div>
                        <div class="user-avatar-small">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Settings</a></li>
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
                        <!-- Quick Actions -->
                        <h5 class="sidebar-title">
                            <i class="bi bi-lightning-charge-fill"></i> Supervisor Actions
                        </h5>
                        <div class="list-group list-group-flush">
                            <a href="#" class="list-group-item list-group-item-action action-card" data-bs-toggle="modal" data-bs-target="#authModal" data-action="create_project">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-plus-circle action-icon"></i>
                                    <div>
                                        <div class="action-text">Proposer un projet</div>
                                        <small class="action-description">Create a new project proposal</small>
                                    </div>
                                </div>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action action-card" data-bs-toggle="modal" data-bs-target="#authModal" data-action="my_projects">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-folder action-icon"></i>
                                    <div>
                                        <div class="action-text">Mes projets</div>
                                        <small class="action-description">View and manage your projects</small>
                                    </div>
                                </div>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action action-card">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar-check action-icon"></i>
                                    <div>
                                        <div class="action-text">Project Reviews</div>
                                        <small class="action-description">Review student submissions</small>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Unit Supervisors -->
                        <h5 class="sidebar-title mt-4">
                            <i class="bi bi-people-fill"></i> Unit Supervisors
                        </h5>
                        <div class="list-group list-group-flush">
                            <?php while($encadrant = $unit_encadrants->fetch_assoc()): ?>
                                <div class="list-group-item supervisor-card">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="bi bi-person-circle" style="font-size: 1.5rem; color: var(--secondary-color);"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($encadrant['grade'] . ' ' . $encadrant['prenom'] . ' ' . $encadrant['nom']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($encadrant['specialite']); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Column -->
                <div class="col-lg-8">
                    <h4 class="section-title animate__animated animate__fadeInRight">
                        <i class="bi bi-journal-bookmark-fill"></i> Unit Projects
                    </h4>
                    
                    <div class="row animate__animated animate__fadeInUp">
                        <?php while($project = $unit_projects->fetch_assoc()): ?>
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
                                            <strong>Supervisor:</strong> <?php echo htmlspecialchars($project['encadrant_grade'] . ' ' . $project['encadrant_prenom'] . ' ' . $project['encadrant_nom']); ?><br>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
            });
            
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
                if (action === 'create_project') {
                    modalTitle = 'Create Project - Authentication';
                } else if (action === 'my_projects') {
                    modalTitle = 'View Projects - Authentication';
                }
                
                document.getElementById('authModalLabel').innerHTML = `
                    <i class="bi bi-shield-lock me-2"></i> ${modalTitle}
                `;
            });
            
            // Form submission handling
            authSubmit.addEventListener('click', async function() {
                try {
                    const formData = new FormData(authForm);
                    const action = formData.get('action');
                    
                    // Show loading state
                    authSubmit.disabled = true;
                    authSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Authenticating...';
                    
                    const response = await fetch('authenticate_action.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const data = await response.json();

                    if (data.success) {
                        // Close the modal
                        const modal = bootstrap.Modal.getInstance(authModal);
                        modal.hide();
                        
                        // Handle different actions
                        switch(action) {
                            case 'create_project':
                                window.location.href = 'create_project.php';
                                break;
                            case 'my_projects':
                                window.location.href = 'supervisor_projects.php';
                                break;
                            default:
                                alert('Authentication successful!');
                        }
                    } else {
                        alert('Authentication failed: ' + (data.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Authentication failed: ' + error.message);
                } finally {
                    // Reset button state
                    authSubmit.disabled = false;
                    authSubmit.innerHTML = 'Authenticate';
                }
            });
        });
    </script>
</body>
</html>