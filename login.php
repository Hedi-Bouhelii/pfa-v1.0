<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Redirect to appropriate dashboard based on role
    switch ($_SESSION['role']) {
        case 'eleve':
            header("Location: student_dashboard.php");
            break;
        case 'encadrant':
            header("Location: encadrant_dashboard.php");
            break;
        case 'admin':
            header("Location: admin_dashboard.php");
            break;
        default:
            header("Location: login.php");
    }
    exit();
}

// Database connection
try {
    $db = new mysqli("localhost", "root", "", "militaryinstituteprojects");
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $unit_name = $db->real_escape_string($_POST['unit_name']);
    $role = $db->real_escape_string($_POST['role']);
    $password = $_POST['password'];

    if (empty($unit_name) || empty($password) || empty($role)) {
        $error = "All fields are required";
    } else {
        // Check unit credentials first for all roles
    $sql = "SELECT * FROM Unit WHERE unit_name = ? AND role = ?";
    $stmt = $db->prepare($sql);
        if (!$stmt) {
            die("Database prepare error: " . $db->error);
        }

    $stmt->bind_param("ss", $unit_name, $role);
        if (!$stmt->execute()) {
            die("Database execute error: " . $stmt->error);
        }

    $result = $stmt->get_result();
        $unit = $result->fetch_assoc();

        if ($unit) {
        // Check both password_plaintext and password_hash
            if ($password === $unit['password_plaintext'] || password_verify($password, $unit['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $unit['unit_id'];
                $_SESSION['role'] = $unit['role'];
                $_SESSION['unit_name'] = $unit['unit_name'];

                // Redirect to appropriate dashboard based on role
                switch ($role) {
                    case 'eleve':
                        header("Location: student_dashboard.php");
                        break;
                    case 'encadrant':
                        header("Location: encadrant_dashboard.php");
                        break;
                    case 'admin':
                        header("Location: admin_dashboard.php");
                        break;
                    default:
                        header("Location: login.php");
                }
            exit();
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "Invalid unit name or role";
        }
    }
}

// Get all units for dropdown
$units = $db->query("SELECT DISTINCT unit_name, role FROM Unit ORDER BY unit_name");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Military Institute Projects</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
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
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }
        
        .login-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            position: relative;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 0.95rem;
            margin-bottom: 0;
            position: relative;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: var(--secondary-color);
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
        
        .btn-login {
            background: linear-gradient(135deg, var(--secondary-color), #2980b9);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 500;
            font-size: 1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
        }
        
        .password-toggle:hover {
            color: var(--dark-color);
        }
        
        .login-footer {
            text-align: center;
            padding: 1rem 2rem;
            background-color: var(--light-color);
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        .login-footer a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        /* Custom select styling */
        .choices__inner {
            border-radius: 8px !important;
            padding: 0.75rem 1rem !important;
            border: 1px solid #ddd !important;
            min-height: auto !important;
            background-color: white !important;
        }
        
        .choices__list--dropdown {
            border-radius: 8px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid #ddd !important;
        }
        
        /* Error message styling */
        .alert-danger {
            border-radius: 8px;
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.2);
            color: var(--danger-color);
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .login-container {
                max-width: 100%;
                box-shadow: none;
            }
            
            body {
                padding: 0;
                background: white;
            }
        }
        
        /* Animation classes */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-shield-alt"></i> Military Institute</h1>
            <p>Secure Project Management System</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="unit_name"><i class="bi bi-building"></i> Unit Name</label>
                    <select class="form-select" id="unit_name" name="unit_name" required>
                        <option value="">Select Unit</option>
                        <?php 
                        if ($units && $units->num_rows > 0) {
                            while($unit = $units->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($unit['unit_name']) . '">' . 
                                     htmlspecialchars($unit['unit_name']) . ' (' . 
                                     ucfirst($unit['role']) . ')</option>';
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="role"><i class="bi bi-person-badge"></i> Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Administrator</option>
                        <option value="encadrant">Supervisor</option>
                        <option value="eleve">Student</option>
                    </select>
                </div>

                <div class="form-group position-relative">
                    <label for="password"><i class="bi bi-lock"></i> Unit Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-login w-100 mt-3">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Login
                </button>
            </form>
        </div>
        
      
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/choices.js@9.0.1/public/assets/scripts/choices.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize enhanced select elements
            const unitSelect = new Choices('#unit_name', {
                searchEnabled: true,
                itemSelectText: '',
                classNames: {
                    containerInner: 'choices__inner',
                    input: 'choices__input',
                }
            });
            
            const roleSelect = new Choices('#role', {
                searchEnabled: false,
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
            
            // Form submission animation
            const form = document.querySelector('form');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            form.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Authenticating...';
            });
        });
    </script>
</body>
</html>