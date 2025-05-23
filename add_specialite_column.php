<?php
// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Check if specialite column exists
$result = $db->query("SHOW COLUMNS FROM Etudiant LIKE 'specialite'");
if ($result->num_rows == 0) {
    // Add specialite column
    $sql = "ALTER TABLE Etudiant ADD COLUMN specialite ENUM('genie civil', 'telecomunication', 'electomecanique', 'genie informatique') NOT NULL DEFAULT 'genie informatique'";
    
    if ($db->query($sql)) {
        echo "Specialite column added successfully";
    } else {
        echo "Error adding specialite column: " . $db->error;
    }
} else {
    echo "Specialite column already exists";
}

$db->close();
?> 