<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !isset($_SESSION['unit_name'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get projects based on user role
$projects = [];
$query = "";

switch ($_SESSION['role']) {
    case 'encadrant':
        // Get projects for this supervisor
        $query = "
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
        ";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        break;

    case 'admin':
        // Get all projects with reservation status
        $query = "
            SELECT p.*, 
                   e.nom as encadrant_nom, e.prenom as encadrant_prenom,
                   COUNT(r.reservation_id) as reservation_count,
                   GROUP_CONCAT(DISTINCT CONCAT(s1.prenom, ' ', s1.nom) SEPARATOR ', ') as students,
                   r.is_approved, r.reservation_date
            FROM Projet p
            LEFT JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
            LEFT JOIN Reservation r ON p.project_id = r.project_id
            LEFT JOIN Etudiant s1 ON r.student1_id = s1.student_id
            LEFT JOIN Etudiant s2 ON r.student2_id = s2.student_id
            GROUP BY p.project_id
            ORDER BY p.created_at DESC
        ";
        $stmt = $db->prepare($query);
        break;

    case 'eleve':
        // Get available projects for student's specialty
        $query = "
            SELECT p.*, 
                   e.nom as encadrant_nom, e.prenom as encadrant_prenom,
                   COUNT(r.reservation_id) as reservation_count
            FROM Projet p
            LEFT JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
            LEFT JOIN Reservation r ON p.project_id = r.project_id
            WHERE p.is_available = TRUE
            AND p.specialite = (
                SELECT specialite 
                FROM Etudiant 
                WHERE student_id = ?
            )
            GROUP BY p.project_id
            ORDER BY p.created_at DESC
        ";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $_SESSION['user_id']);
        break;
}

$stmt->execute();
$result = $stmt->get_result();
$projects = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - Military Institute</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .project-card {
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .project-card:hover {
            transform: translateY(-5px);
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Military Institute Projects</a>
            <div class="d-flex">
                <span class="navbar-text me-3">
                    Connected as: <?php echo ucfirst($_SESSION['role']); ?>
                </span>
                <a href="<?php echo $_SESSION['role']; ?>_dashboard.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Projects</h2>
        
        <?php if ($_SESSION['role'] === 'encadrant'): ?>
            <a href="create_project.php" class="btn btn-primary mb-3">
                <i class="bi bi-plus-circle"></i> Create New Project
            </a>
        <?php endif; ?>

        <div class="row">
            <?php foreach ($projects as $project): ?>
                <div class="col-md-6">
                    <div class="card project-card">
                        <div class="card-body">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <span class="badge <?php echo $project['is_approved'] === null ? 'bg-warning' : ($project['is_approved'] ? 'bg-success' : 'bg-danger'); ?> status-badge">
                                    <?php echo $project['is_approved'] === null ? 'Pending' : ($project['is_approved'] ? 'Approved' : 'Rejected'); ?>
                                </span>
                            <?php endif; ?>

                            <h5 class="card-title"><?php echo htmlspecialchars($project['titre']); ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted">
                                <?php echo htmlspecialchars($project['specialite']); ?> - 
                                <?php echo ucfirst($project['nombre_eleves']); ?>
                            </h6>

                            <p class="card-text"><?php echo htmlspecialchars(substr($project['description'], 0, 150)) . '...'; ?></p>

                            <?php if ($_SESSION['role'] === 'admin' && $project['encadrant_nom']): ?>
                                <p class="card-text">
                                    <small class="text-muted">
                                        Supervisor: <?php echo htmlspecialchars($project['encadrant_prenom'] . ' ' . $project['encadrant_nom']); ?>
                                    </small>
                                </p>
                            <?php endif; ?>

                            <?php if ($project['students']): ?>
                                <p class="card-text">
                                    <small class="text-muted">
                                        Students: <?php echo htmlspecialchars($project['students']); ?>
                                    </small>
                                </p>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center">
                                <a href="project_details.php?id=<?php echo $project['project_id']; ?>" class="btn btn-primary btn-sm">
                                    View Details
                                </a>

                                <?php if ($_SESSION['role'] === 'encadrant' && $project['is_available']): ?>
                                    <div>
                                        <a href="edit_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <button class="btn btn-danger btn-sm" onclick="deleteProject(<?php echo $project['project_id']; ?>)">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <?php if ($_SESSION['role'] === 'admin' && $project['is_approved'] === null): ?>
                                    <div>
                                        <button class="btn btn-success btn-sm" onclick="approveReservation(<?php echo $project['project_id']; ?>)">
                                            <i class="bi bi-check-circle"></i> Approve
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="rejectReservation(<?php echo $project['project_id']; ?>)">
                                            <i class="bi bi-x-circle"></i> Reject
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <?php if ($_SESSION['role'] === 'eleve' && $project['is_available']): ?>
                                    <button class="btn btn-success btn-sm" onclick="reserveProject(<?php echo $project['project_id']; ?>)">
                                        <i class="bi bi-bookmark-plus"></i> Reserve
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteProject(projectId) {
            if (confirm('Are you sure you want to delete this project?')) {
                fetch('delete_project.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ project_id: projectId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function reserveProject(projectId) {
            if (confirm('Are you sure you want to reserve this project?')) {
                fetch('reserve_project.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ project_id: projectId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function approveReservation(projectId) {
            if (confirm('Are you sure you want to approve this reservation?')) {
                fetch('approve_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ project_id: projectId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }

        function rejectReservation(projectId) {
            const reason = prompt('Please enter rejection reason:');
            if (reason !== null) {
                fetch('reject_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        project_id: projectId,
                        reason: reason
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html> 