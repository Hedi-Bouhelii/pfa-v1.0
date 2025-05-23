<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['unit_name'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get admin information (only for personal actions)
$admin_info = $db->query("
    SELECT a.*, u.unit_name 
    FROM Administrateur a 
    LEFT JOIN Unit u ON a.unit_id = u.unit_id 
    WHERE a.admin_id = " . intval($_SESSION['user_id'])
);

if (!$admin_info) {
    die("Error in admin query: " . $db->error);
}

$admin_info = $admin_info->fetch_assoc();
if (!$admin_info) {
    die("Error: Admin information not found for ID: " . $_SESSION['user_id']);
}

// Get statistics
$stats = [];
$queries = [
    'total_projects' => "SELECT COUNT(*) as count FROM Projet",
    'total_students' => "SELECT COUNT(*) as count FROM Etudiant",
    'total_encadrants' => "SELECT COUNT(*) as count FROM Encadrant",
    'pending_reservations' => "SELECT COUNT(*) as count FROM Reservation WHERE is_approved IS NULL",
    'available_projects' => "SELECT COUNT(*) as count FROM Projet WHERE project_id NOT IN (SELECT project_id FROM Reservation WHERE is_approved = 1)",
    'reserved_projects' => "SELECT COUNT(*) as count FROM Projet WHERE project_id IN (SELECT project_id FROM Reservation WHERE is_approved = 1)"
];

foreach ($queries as $key => $query) {
    $result = $db->query($query);
    if ($result === false) {
        die("Error in query for $key: " . $db->error);
    }
    $stats[$key] = $result->fetch_assoc()['count'];
}

// Get recent activities (without personal information)
$recent_activities = $db->query("
    SELECT ph.*, p.titre as project_title
    FROM HistoireProjet ph
    JOIN Projet p ON ph.project_id = p.project_id
    ORDER BY ph.changed_at DESC
    LIMIT 10
");

if (!$recent_activities) {
    die("Error in recent activities query: " . $db->error);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Military Institute Projects</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
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
            padding: 0.8rem 0;
        }
        
        .navbar-brand {
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
        
        .stat-card {
            background-color: #fff;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: all 0.3s ease;
            border-left: 4px solid var(--secondary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .action-card {
            border: none;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            padding: 1rem;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            background-color: var(--light-color);
        }
        
        .action-icon {
            font-size: 1.25rem;
            color: var(--secondary-color);
            margin-right: 10px;
        }
        
        .section-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--light-color);
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background-color: var(--primary-color);
            color: white;
        }
        
        .table th {
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }
        
        .modal-content {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            border-bottom: none;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shield-lock"></i> Military Institute Admin
            </a>
            <div class="d-flex">
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container dashboard-container">
        <div class="row">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-4 section-title">Administration</h5>
                        <div class="list-group list-group-flush">
                            <a href="#" class="list-group-item list-group-item-action action-card" data-bs-toggle="modal" data-bs-target="#authModal" data-action="manage_projects">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-folder action-icon"></i>
                                    <div>
                                        <div>Gérer les projets</div>
                                        <small class="text-muted">View, edit, or delete all projects</small>
                                    </div>
                                </div>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action action-card" data-bs-toggle="modal" data-bs-target="#authModal" data-action="manage_users">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-people action-icon"></i>
                                    <div>
                                        <div>Gérer les utilisateurs</div>
                                        <small class="text-muted">Add, modify, or delete user accounts</small>
                                    </div>
                                </div>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action action-card" data-bs-toggle="modal" data-bs-target="#authModal" data-action="manage_roles">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-shield-lock action-icon"></i>
                                    <div>
                                        <div>Gérer les droits d'accès</div>
                                        <small class="text-muted">Define roles and permissions</small>
                                    </div>
                                </div>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action action-card" data-bs-toggle="modal" data-bs-target="#authModal" data-action="platform_settings">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-gear action-icon"></i>
                                    <div>
                                        <div>Paramétrer la plateforme</div>
                                        <small class="text-muted">Modify global settings</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <h4 class="section-title">Platform Statistics</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['total_projects']; ?></div>
                            <div class="stat-label">Total Projects</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['available_projects']; ?></div>
                            <div class="stat-label">Available Projects</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['reserved_projects']; ?></div>
                            <div class="stat-label">Reserved Projects</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['total_encadrants']; ?></div>
                            <div class="stat-label">Total Supervisors</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['pending_reservations']; ?></div>
                            <div class="stat-label">Pending Reservations</div>
                        </div>
                    </div>
                </div>

                <h4 class="section-title mt-5">Recent Activities</h4>
                <div class="card border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Action</th>
                                        <th>Project</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($activity = $recent_activities->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i', strtotime($activity['changed_at'])); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst($activity['change_type']); ?></span></td>
                                            <td><?php echo htmlspecialchars($activity['project_title']); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Authentication Modal -->
    <div class="modal fade" id="authModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i> Authentication Required</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="authForm">
                        <input type="hidden" id="action" name="action">
                        <div class="mb-3">
                            <label for="matricule" class="form-label">Matricule</label>
                            <input type="text" class="form-control" id="matricule" name="matricule" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="authSubmit">Authenticate</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const authModal = document.getElementById('authModal');
            const authForm = document.getElementById('authForm');
            const actionInput = document.getElementById('action');
            const authSubmit = document.getElementById('authSubmit');

            // Set action when modal is opened
            authModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const action = button.getAttribute('data-action');
                actionInput.value = action;
            });

            // Handle form submission
            authSubmit.addEventListener('click', function() {
                const formData = new FormData(authForm);
                
                fetch('authenticate_action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.redirect;
                    } else {
                        alert('Authentication failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred during authentication');
                });
            });
        });
    </script>
</body>
</html>