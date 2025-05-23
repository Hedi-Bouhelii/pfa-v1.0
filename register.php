<div class="mb-3">
    <label for="specialite" class="form-label required-field">Spécialité</label>
    <select class="form-select" id="specialite" name="specialite" required>
        <option value="">Sélectionner une spécialité</option>
        <option value="genie civil">Génie Civil</option>
        <option value="telecomunication">Télécommunication</option>
        <option value="electomecanique">Électromécanique</option>
        <option value="genie informatique">Génie Informatique</option>
    </select>
</div>

<?php

$specialite = $_POST['specialite'] ?? '';

// Validate specialty
if (empty($specialite) || !in_array($specialite, ['genie civil', 'telecomunication', 'electomecanique', 'genie informatique'])) {
    $errors[] = "La spécialité est invalide";
}

$stmt = $db->prepare("
    INSERT INTO Etudiant (
        matricule, nom, prenom, email, password, 
        phone, class_id, unit_id, specialite
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssssiis",
    $matricule, $nom, $prenom, $email, $hashed_password,
    $phone, $class_id, $unit_id, $specialite
); 