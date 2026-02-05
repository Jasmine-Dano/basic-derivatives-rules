<?php
// router.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user role from session
$user_role = $_SESSION['user_role'] ?? '';

// Redirect to appropriate dashboard based on role
switch ($user_role) {
    case 'doctor':
        header('Location: doctor_dashboard.php');
        break;
    case 'nurse':
        header('Location: nurse_dashboard.php');
        break;
    case 'patient':
        header('Location: patient_dashboard.php');
        break;
    default:
        // If no specific role, use main dashboard
        header('Location: AndaEhr.php');
}
exit();