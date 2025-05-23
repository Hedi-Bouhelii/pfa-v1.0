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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Military Institute Projects</a>
            <div class="d-flex">
                <span class="navbar-text me-3">
                    Connected as: Supervisor
                </span>
                <a href="encadrant_dashboard.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container form-container">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title mb-4">Créer un Nouveau Projet</h2>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        Le projet a été créé avec succès!
                        <a href="encadrant_dashboard.php" class="alert-link">Retour au tableau de bord</a>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" id="projectForm" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="titre" class="form-label required-field">Titre du Projet</label>
                            <input type="text" class="form-control" id="titre" name="titre" required
                                   value="<?php echo htmlspecialchars($_POST['titre'] ?? ''); ?>">
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
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="organisme" class="form-label">Organisme</label>
                            <input type="text" class="form-control" id="organisme" name="organisme"
                                   value="<?php echo htmlspecialchars($_POST['organisme'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Adresse</label>
                        <textarea class="form-control" id="address" name="address" rows="1"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label required-field">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="objectif" class="form-label required-field">Objectif</label>
                        <textarea class="form-control" id="objectif" name="objectif" rows="3" required><?php echo htmlspecialchars($_POST['objectif'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="resultats_attendus" class="form-label required-field">Résultats Attendus</label>
                        <textarea class="form-control" id="resultats_attendus" name="resultats_attendus" rows="3" required><?php echo htmlspecialchars($_POST['resultats_attendus'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="encadrant_dashboard.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn btn-primary">Créer le Projet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('projectForm');

            // Form validation
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    </script>
</body>
</html> 