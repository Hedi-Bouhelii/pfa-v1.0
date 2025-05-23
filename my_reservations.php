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

// Get student information
$student_info = $db->query("
    SELECT e.*, c.class_name as classe_nom, u.unit_name
    FROM Etudiant e 
    LEFT JOIN Classe c ON e.class_id = c.class_id 
    LEFT JOIN Unit u ON e.unit_id = u.unit_id 
    WHERE e.student_id = " . intval($_SESSION['user_id'])
);

if (!$student_info) {
    die("Error in student query: " . $db->error);
}

$student_info = $student_info->fetch_assoc();
if (!$student_info) {
    session_unset();
    session_destroy();
    header("Location: login.php?error=invalid_session");
    exit();
}

// Get student's reservations
$reservations_query = "
    SELECT r.*, 
           p.titre as project_title, p.description as project_description, p.nombre_eleves,
           e.grade as encadrant_grade, e.prenom as encadrant_prenom, e.nom as encadrant_nom,
           e1.prenom as student1_prenom, e1.nom as student1_nom,
           e2.prenom as student2_prenom, e2.nom as student2_nom
    FROM Reservation r
    JOIN Projet p ON r.project_id = p.project_id
    JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
    JOIN Etudiant e1 ON r.student1_id = e1.student_id
    LEFT JOIN Etudiant e2 ON r.student2_id = e2.student_id
    WHERE r.student1_id = " . intval($_SESSION['user_id']) . " 
       OR r.student2_id = " . intval($_SESSION['user_id']) . "
    ORDER BY r.reservation_date DESC";

$reservations = $db->query($reservations_query);

if (!$reservations) {
    die("Error in reservations query: " . $db->error);
}

// Debug information
$debug_info = [
    'user_id' => $_SESSION['user_id'],
    'reservations_count' => $reservations->num_rows,
    'query' => $reservations_query
];
echo "<!-- Debug Information: " . json_encode($debug_info) . " -->";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - Military Institute Projects</title>
    
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
        
        .user-avatar-small {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--secondary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0;
        }
        
        .reservation-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            height: 100%;
            margin-bottom: 1.5rem;
        }
        
        .reservation-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }
        
        .reservation-card .card-body {
            padding: 1.5rem;
        }
        
        .reservation-card .card-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.75rem;
        }
        
        .reservation-card .card-text {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
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
        
        .btn-cancel {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border: none;
        }
        
        .btn-cancel:hover {
            background-color: rgba(231, 76, 60, 0.2);
        }
        
        .section-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .empty-state {
            background: white;
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }
        
        .empty-state-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .empty-state-text {
            color: #7f8c8d;
            margin-bottom: 1.5rem;
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
            <a class="navbar-brand" href="student_dashboard.php">
                <i class="fas fa-shield-alt"></i> Military Institute Projects
            </a>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="me-3 text-end d-none d-sm-block">
                            <div class="fw-semibold"><?php echo htmlspecialchars($student_info['prenom'] . ' ' . $student_info['nom']); ?></div>
                            <div class="small text-light">ID: <?php echo htmlspecialchars($student_info['matricule']); ?></div>
                        </div>
                        <div class="user-avatar-small">
                            <?php echo substr($student_info['prenom'], 0, 1) . substr($student_info['nom'], 0, 1); ?>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="student_dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="page-header animate__animated animate__fadeIn">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="page-title"><i class="bi bi-bookmark-check-fill"></i> My Reservations</h1>
                <a href="student_dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($reservations->num_rows === 0): ?>
            <div class="empty-state animate__animated animate__fadeIn">
                <div class="empty-state-icon">
                    <i class="bi bi-journal-bookmark"></i>
                </div>
                <h3 class="empty-state-title">No Reservations Found</h3>
                <p class="empty-state-text">You haven't made any project reservations yet. Browse available projects to get started.</p>
                <a href="student_dashboard.php" class="btn btn-primary">
                    <i class="bi bi-search"></i> Browse Projects
                </a>
            </div>
        <?php else: ?>
            <div class="row animate__animated animate__fadeIn">
                <!-- Example Hardcoded Reservations (would be replaced by PHP loop in production) -->
                <div class="col-md-6">
                    <div class="reservation-card">
                        <div class="card-body">
                            <span class="status-badge badge-approved">Approved</span>
                            <h5 class="card-title">Secure Military Communication Protocol</h5>
                            <p class="card-text">Development of an encrypted communication protocol for field operations using quantum cryptography principles.</p>
                            
                            <div class="mb-3">
                                <h6 class="section-title"><i class="bi bi-info-circle"></i> Project Details</h6>
                                <div class="project-meta">
                                    <strong>Type:</strong> Research & Development<br>
                                    <strong>Supervisor:</strong> Col. Jean Dupont<br>
                                    <strong>Reserved on:</strong> 15/03/2023 14:30<br>
                                    <strong>Approved on:</strong> 18/03/2023 09:15
                                </div>
                            </div>

                            <div class="mb-3">
                                <h6 class="section-title"><i class="bi bi-people-fill"></i> Group Partner</h6>
                                <div class="project-meta">
                                    <strong>Name:</strong> Cadet Marie Curie<br>
                                    <strong>ID:</strong> MID-2023-042
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="project_details.php?id=1" class="btn btn-details btn-action">
                                    <i class="bi bi-info-circle"></i> View Project
                                </a>
                                <button class="btn btn-secondary btn-action" disabled>
                                    <i class="bi bi-check-circle"></i> Approved
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="reservation-card">
                        <div class="card-body">
                            <span class="status-badge badge-pending">Pending</span>
                            <h5 class="card-title">AI for Threat Detection</h5>
                            <p class="card-text">Implementing machine learning algorithms to detect potential threats in surveillance footage with high accuracy.</p>
                            
                            <div class="mb-3">
                                <h6 class="section-title"><i class="bi bi-info-circle"></i> Project Details</h6>
                                <div class="project-meta">
                                    <strong>Type:</strong> Artificial Intelligence<br>
                                    <strong>Supervisor:</strong> Maj. Pierre Lambert<br>
                                    <strong>Reserved on:</strong> 02/05/2023 10:45<br>
                                    <strong>Status:</strong> Awaiting approval
                                </div>
                            </div>

                            <div class="alert alert-warning p-2 mb-3" role="alert">
                                <small><i class="bi bi-info-circle"></i> Your project proposal is under review by the committee</small>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="project_details.php?id=2" class="btn btn-details btn-action">
                                    <i class="bi bi-info-circle"></i> View Project
                                </a>
                                <button class="btn btn-cancel btn-action" onclick="cancelReservation(2)">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- PHP Generated Reservations -->
                <?php while($reservation = $reservations->fetch_assoc()): ?>
                    <div class="col-md-6">
                        <div class="reservation-card">
                            <div class="card-body">
                                <span class="status-badge <?php 
                                    echo $reservation['is_approved'] === null ? 'badge-pending' : 
                                        ($reservation['is_approved'] ? 'badge-approved' : 'badge-rejected'); 
                                ?>">
                                    <?php 
                                    echo $reservation['is_approved'] === null ? 'Pending' : 
                                        ($reservation['is_approved'] ? 'Approved' : 'Rejected'); 
                                    ?>
                                </span>
                                <h5 class="card-title"><?php echo htmlspecialchars($reservation['project_title']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($reservation['project_description'], 0, 100)) . '...'; ?></p>
                                
                                <div class="mb-3">
                                    <h6 class="section-title"><i class="bi bi-info-circle"></i> Project Details</h6>
                                    <div class="project-meta">
                                        <strong>Type:</strong> <?php echo htmlspecialchars($reservation['type']); ?><br>
                                        <strong>Supervisor:</strong> <?php echo htmlspecialchars($reservation['encadrant_grade'] . ' ' . $reservation['encadrant_prenom'] . ' ' . $reservation['encadrant_nom']); ?><br>
                                        <strong>Reserved on:</strong> <?php echo date('d/m/Y H:i', strtotime($reservation['reservation_date'])); ?>
                                        <?php if ($reservation['is_approved'] === 1): ?>
                                            <br><strong>Approved on:</strong> <?php echo date('d/m/Y H:i', strtotime($reservation['approval_date'])); ?>
                                        <?php elseif ($reservation['is_approved'] === 0): ?>
                                            <br><strong>Rejected on:</strong> <?php echo date('d/m/Y H:i', strtotime($reservation['approval_date'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($reservation['nombre_eleves'] === 'binome'): ?>
                                    <div class="mb-3">
                                        <h6 class="section-title"><i class="bi bi-people-fill"></i> Group Partner</h6>
                                        <div class="project-meta">
                                            <?php if ($reservation['student2_id']): ?>
                                                <strong>Name:</strong> <?php echo htmlspecialchars($reservation['student2_prenom'] . ' ' . $reservation['student2_nom']); ?><br>
                                                <strong>ID:</strong> <?php echo htmlspecialchars($reservation['student2_matricule']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No partner selected</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex gap-2">
                                    <a href="project_details.php?id=<?php echo $reservation['project_id']; ?>" class="btn btn-details btn-action">
                                        <i class="bi bi-info-circle"></i> View Project
                                    </a>
                                    <?php if ($reservation['is_approved'] === null): ?>
                                        <button class="btn btn-cancel btn-action" onclick="cancelReservation(<?php echo $reservation['reservation_id']; ?>)">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    <?php elseif ($reservation['is_approved'] === 1): ?>
                                        <button class="btn btn-secondary btn-action" disabled>
                                            <i class="bi bi-check-circle"></i> Approved
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-action" disabled>
                                            <i class="bi bi-x-circle"></i> Rejected
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function cancelReservation(reservationId) {
            if (confirm('Are you sure you want to cancel this reservation? This action cannot be undone.')) {
                // Show loading state
                const button = event.target;
                const originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                
                fetch('cancel_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ reservation_id: reservationId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Reservation cancelled successfully', 'success');
                        // Reload the page after a short delay
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast(data.message || 'Failed to cancel reservation', 'danger');
                        button.disabled = false;
                        button.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while canceling the reservation', 'danger');
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
            }
        }

        function showToast(message, type = 'info') {
            // Create toast element if it doesn't exist
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
            
            const toastId = 'toast-' + Date.now();
            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `toast show align-items-center text-white bg-${type} border-0`;
            toast.style.marginBottom = '10px';
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : type === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto-remove toast after 5 seconds
            setTimeout(() => {
                const toastElement = document.getElementById(toastId);
                if (toastElement) {
                    toastElement.classList.remove('show');
                    setTimeout(() => toastElement.remove(), 300);
                }
            }, 5000);
            
            // Add click handler to close button
            toast.querySelector('[data-bs-dismiss="toast"]').addEventListener('click', () => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            });
        }
    </script>
</body>
</html>