<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

function dashboard_user_role(): string
{
    return strtolower(trim((string)(
        $_SESSION['user_role'] ??
        ($_SESSION['user']['role'] ?? '')
    )));
}

function dashboard_require_role(array $roles): void
{
    $role = dashboard_user_role();
    $allowed = array_map('strtolower', $roles);

    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function dashboard_escape(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
