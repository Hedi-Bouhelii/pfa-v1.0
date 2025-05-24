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

// Get encadrant information
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

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $titre = trim($_POST['titre'] ?? '');
    $specialite = $_POST['specialite'] ?? '';
    $nombre_eleves = $_POST['nombre_eleves'] ?? '';
    $organisme = trim($_POST['organisme'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $objectif = trim($_POST['objectif'] ?? '');
    $resultats_attendus = trim($_POST['resultats_attendus'] ?? '');

    // Validation
    if (empty($titre)) {
        $errors[] = "Le titre est requis";
    } elseif (strlen($titre) > 200) {
        $errors[] = "Le titre ne doit pas dépasser 200 caractères";
    }

    if (empty($specialite) || !in_array($specialite, ['genie civil', 'telecomunication', 'electomecanique', 'genie informatique'])) {
        $errors[] = "La spécialité est invalide";
    }

    if (empty($nombre_eleves) || !in_array($nombre_eleves, ['monome', 'binome'])) {
        $errors[] = "Le nombre d'élèves est invalide";
    }

    if (empty($description)) {
        $errors[] = "La description est requise";
    }

    if (empty($objectif)) {
        $errors[] = "L'objectif est requis";
    }

    if (empty($resultats_attendus)) {
        $errors[] = "Les résultats attendus sont requis";
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email est invalide";
    }

    if (!empty($phone) && !preg_match('/^[0-9+\s-]{8,20}$/', $phone)) {
        $errors[] = "Le numéro de téléphone est invalide";
    }

    // If no errors, insert the project
    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO Projet (
                titre, specialite, nombre_eleves, encadrant_id, 
                organisme, address, phone, email, 
                description, objectif, resultats_attendus,
                is_available
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
        ");

        $stmt->bind_param(
            "sssisssssss",
            $titre, $specialite, $nombre_eleves, $_SESSION['user_id'],
            $organisme, $address, $phone, $email,
            $description, $objectif, $resultats_attendus
        );

        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = "Erreur lors de la création du projet: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Projet - Military Institute Projects</title>
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
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --card-shadow-hover: 0 8px 30px rgba(0, 0, 0, 0.12);
            --border-radius: 12px;
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
        
        .form-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 15px;
        }
        
        .form-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            padding: 1.75rem;
            border-bottom: none;
        }
        
        .form-body {
            padding: 2.5rem;
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger-color);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(58, 110, 165, 0.25);
        }
        
        textarea.form-control {
            min-height: 120px;
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
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-3px);
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .success-icon {
            color: var(--success-color);
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }
        
        .error-icon {
            color: var(--danger-color);
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .form-section-title {
            position: relative;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            color: var(--primary-color);
        }
        
        .form-section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary-color), var(--accent-color));
            border-radius: 2px;
        }
        
        .input-group-text {
            background-color: rgba(58, 110, 165, 0.1);
            border-color: #ddd;
            color: var(--secondary-color);
        }
        
        .invalid-feedback {
            display: none;
            color: var(--danger-color);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        .was-validated .form-control:invalid,
        .was-validated .form-select:invalid,
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: var(--danger-color);
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23e74c3c'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23e74c3c' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .was-validated .form-control:invalid ~ .invalid-feedback,
        .was-validated .form-select:invalid ~ .invalid-feedback,
        .form-control.is-invalid ~ .invalid-feedback,
        .form-select.is-invalid ~ .invalid-feedback {
            display: block;
        }
        
        .was-validated .form-control:valid,
        .was-validated .form-select:valid,
        .form-control.is-valid,
        .form-select.is-valid {
            border-color: var(--success-color);
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2327ae60' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        @media (max-width: 768px) {
            .form-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shield-lock me-2"></i> Military Institute Projects
            </a>
            <div class="d-flex align-items-center">
                <span class="navbar-text text-white me-3">
                    <i class="bi bi-person-badge me-1"></i> Connecté en tant que: Superviseur
                </span>
                <a href="encadrant_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-speedometer2 me-1"></i> Tableau de bord
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="form-container">
        <div class="card form-card">
            <div class="form-header">
                <h2 class="mb-0">
                    <i class="bi bi-file-earmark-plus me-2"></i>
                    Créer un Nouveau Projet
                </h2>
            </div>
            
            <div class="form-body">
                <?php if ($success): ?>
                    <div class="alert alert-success d-flex align-items-center mb-4">
                        <i class="bi bi-check-circle-fill success-icon"></i>
                        <div>
                            <strong>Succès!</strong> Le projet a été créé avec succès!
                            <a href="encadrant_dashboard.php" class="alert-link">Retour au tableau de bord</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4">
                        <i class="bi bi-exclamation-triangle-fill error-icon"></i>
                        <div>
                            <strong>Erreurs détectées:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="projectForm" class="needs-validation" novalidate>
                    <!-- Project Information Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="bi bi-info-circle me-2"></i>
                            Informations de Base
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="titre" class="form-label required-field">Titre du Projet</label>
                                <input type="text" class="form-control" id="titre" name="titre" required
                                       value="<?php echo htmlspecialchars($_POST['titre'] ?? ''); ?>">
                                <div class="invalid-feedback">
                                    Veuillez fournir un titre pour le projet.
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="specialite" class="form-label required-field">Spécialité</label>
                                <select class="form-select" id="specialite" name="specialite" required>
                                    <option value="">Sélectionner une spécialité</option>
                                    <option value="genie civil" <?php echo ($_POST['specialite'] ?? '') === 'genie civil' ? 'selected' : ''; ?>>Génie Civil</option>
                                    <option value="telecomunication" <?php echo ($_POST['specialite'] ?? '') === 'telecomunication' ? 'selected' : ''; ?>>Télécommunication</option>
                                    <option value="electomecanique" <?php echo ($_POST['specialite'] ?? '') === 'electomecanique' ? 'selected' : ''; ?>>Électromécanique</option>
                                    <option value="genie informatique" <?php echo ($_POST['specialite'] ?? '') === 'genie informatique' ? 'selected' : ''; ?>>Génie Informatique</option>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner une spécialité.
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre_eleves" class="form-label required-field">Nombre d'Élèves</label>
                                <select class="form-select" id="nombre_eleves" name="nombre_eleves" required>
                                    <option value="">Sélectionner le nombre d'élèves</option>
                                    <option value="monome" <?php echo ($_POST['nombre_eleves'] ?? '') === 'monome' ? 'selected' : ''; ?>>Monôme</option>
                                    <option value="binome" <?php echo ($_POST['nombre_eleves'] ?? '') === 'binome' ? 'selected' : ''; ?>>Binôme</option>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner le nombre d'élèves.
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="organisme" class="form-label">Organisme</label>
                                <input type="text" class="form-control" id="organisme" name="organisme"
                                       value="<?php echo htmlspecialchars($_POST['organisme'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="bi bi-person-lines-fill me-2"></i>
                            Informations de Contact
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Téléphone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           pattern="[0-9]{10}" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                                <div class="invalid-feedback">
                                    Veuillez fournir un numéro de téléphone valide (10 chiffres).
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                                <div class="invalid-feedback">
                                    Veuillez fournir une adresse email valide.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Adresse</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                <textarea class="form-control" id="address" name="address" rows="1"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Project Details Section -->
                    <div class="form-section">
                        <h3 class="form-section-title">
                            <i class="bi bi-file-text me-2"></i>
                            Détails du Projet
                        </h3>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label required-field">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="invalid-feedback">
                                Veuillez fournir une description du projet.
                            </div>
                            <small class="text-muted">Décrivez en détail le projet et ses caractéristiques principales.</small>
                        </div>

                        <div class="mb-3">
                            <label for="objectif" class="form-label required-field">Objectif</label>
                            <textarea class="form-control" id="objectif" name="objectif" rows="4" required><?php echo htmlspecialchars($_POST['objectif'] ?? ''); ?></textarea>
                            <div class="invalid-feedback">
                                Veuillez définir les objectifs du projet.
                            </div>
                            <small class="text-muted">Quels sont les buts principaux que ce projet vise à atteindre?</small>
                        </div>

                        <div class="mb-3">
                            <label for="resultats_attendus" class="form-label required-field">Résultats Attendus</label>
                            <textarea class="form-control" id="resultats_attendus" name="resultats_attendus" rows="4" required><?php echo htmlspecialchars($_POST['resultats_attendus'] ?? ''); ?></textarea>
                            <div class="invalid-feedback">
                                Veuillez décrire les résultats attendus du projet.
                            </div>
                            <small class="text-muted">Quels sont les livrables ou résultats concrets attendus à la fin du projet?</small>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between pt-3">
                        <a href="encadrant_dashboard.php" class="btn btn-secondary btn-action">
                            <i class="bi bi-x-circle"></i> Annuler
                        </a>
                        <button type="submit" class="btn btn-primary btn-action">
                            <i class="bi bi-check-circle"></i> Créer le Projet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const form = document.getElementById('projectForm');
            
            // Real-time validation
            form.addEventListener('input', function(e) {
                const input = e.target;
                if (input.checkValidity()) {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                } else {
                    input.classList.remove('is-valid');
                    input.classList.add('is-invalid');
                }
            });
            
            // Form submission
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
                
                // Custom validation for textareas
                const textareas = form.querySelectorAll('textarea[required]');
                textareas.forEach(textarea => {
                    if (textarea.value.trim() === '') {
                        textarea.classList.add('is-invalid');
                        textarea.classList.remove('is-valid');
                        event.preventDefault();
                    } else {
                        textarea.classList.add('is-valid');
                        textarea.classList.remove('is-invalid');
                    }
                });
            });
            
            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 10) {
                        value = value.substring(0, 10);
                    }
                    e.target.value = value;
                });
            }
        });
    </script>
</body>
</html>