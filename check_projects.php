<?php
// Database connection
$db = new mysqli("localhost", "root", "", "militaryinstituteprojects");

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get all projects
$projects = $db->query("
    SELECT p.*, e.nom as encadrant_nom, e.prenom as encadrant_prenom
    FROM Projet p
    LEFT JOIN Encadrant e ON p.encadrant_id = e.encadrant_id
    ORDER BY p.created_at DESC
");

if (!$projects) {
    die("Error in projects query: " . $db->error);
}

echo "<h2>All Projects in Database</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Title</th><th>Specialty</th><th>Available</th><th>Supervisor</th></tr>";

while ($project = $projects->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($project['project_id']) . "</td>";
    echo "<td>" . htmlspecialchars($project['titre']) . "</td>";
    echo "<td>" . htmlspecialchars($project['specialite']) . "</td>";
    echo "<td>" . ($project['is_available'] ? 'Yes' : 'No') . "</td>";
    echo "<td>" . htmlspecialchars($project['encadrant_prenom'] . ' ' . $project['encadrant_nom']) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Get student information
$student_id = 1; // Replace with actual student ID for testing
$student = $db->query("
    SELECT * FROM Etudiant WHERE student_id = " . intval($student_id)
);

if ($student) {
    $student_info = $student->fetch_assoc();
    echo "<h2>Student Information</h2>";
    echo "<pre>";
    print_r($student_info);
    echo "</pre>";
}

$db->close();
?> 