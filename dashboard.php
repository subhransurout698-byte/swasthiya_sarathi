<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = strtolower(trim((string)($_SESSION['user_role'] ?? '')));

switch ($role) {
    case 'admin':
    case 'administrator':
    case 'super_admin':
        header('Location: admin/dashboard.php');
        break;

    case 'doctor':
        header('Location: doctor/dashboard.php');
        break;

    case 'health_worker':
    case 'healthcare_worker':
    case 'staff':
    case 'nurse':
        header('Location: health_worker/dashboard.php');
        break;

    case 'patient':
    case 'user':
    case 'user_patient':
    case 'beneficiary':
        header('Location: user/dashboard.php');
        break;

    default:
        session_unset();
        session_destroy();
        header('Location: login.php?error=invalid_role');
        break;
}

exit;
