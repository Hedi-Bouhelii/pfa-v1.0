<?php
session_start();

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

error_log("AUTH-ACTION DEBUG: Starting authentication for role: " . ($_SESSION['role'] ?? 'undefined'));

// Verify this is a supervisor auth request
if (($_SESSION['role'] ?? null) !== 'encadrant') {
    error_log("AUTH-ACTION DEBUG: Invalid role access attempt");
    header("Location: login.php");
    exit();
}

// Set authenticated flag
$_SESSION['authenticated_encadrant'] = $_SESSION['user_id'];
session_regenerate_id(true); // Prevent session fixation
unset($_SESSION['auth_attempted']); // Clear attempt flag

error_log("AUTH-ACTION DEBUG: Authentication granted for encadrant ID: " . $_SESSION['user_id']);

// Redirect back to target page
$target = $_SESSION['auth_redirect_target'] ?? 'encadrant_dashboard.php';
error_log("AUTH-ACTION DEBUG: Redirecting to: " . $target);
header("Location: $target");
exit(); 