<?php
// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Add specialite column to Etudiant table
$sql = "ALTER TABLE Etudiant 
        ADD COLUMN specialite ENUM('genie civil', 'telecomunication', 'electomecanique', 'genie informatique') 
        NOT NULL DEFAULT 'genie informatique'";

if ($db->query($sql)) {
    echo "Specialite column added successfully to Etudiant table";
} else {
    echo "Error adding specialite column: " . $db->error;
}

$db->close();
?> 