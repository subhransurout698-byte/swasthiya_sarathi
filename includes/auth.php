<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';


function require_login(): void
{
    if (empty($_SESSION['user_id'])) {

        header('Location: login.php');

        exit;
    }
}


function require_role(array $allowedRoles): void
{
    require_login();

    $role = $_SESSION['user']['role']
        ?? $_SESSION['user_role']
        ?? '';

    if (!in_array($role, $allowedRoles, true)) {

        http_response_code(403);

        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Access denied</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f4f7fb;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                }

                .box {
                    background: white;
                    padding: 40px;
                    border-radius: 18px;
                    max-width: 500px;
                    text-align: center;
                    box-shadow: 0 15px 50px rgba(0,0,0,.08);
                }

                h1 {
                    color: #123b63;
                }

                a {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 12px 20px;
                    background: #0b5cab;
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                }
            </style>
        </head>
        <body>
            <div class="box">
                <h1>Access restricted</h1>
                <p>Your account does not have permission to perform this action.</p>
                <a href="dashboard.php">Return to Dashboard</a>
            </div>
        </body>
        </html>';

        exit;
    }
}