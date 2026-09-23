<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Swasthya Saarthi - CSRF Protection
|--------------------------------------------------------------------------
| Generates and validates a CSRF token stored in the PHP session.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Start session if it isn't already running
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Generate / return CSRF token
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


/*
|--------------------------------------------------------------------------
| Validate CSRF token
|--------------------------------------------------------------------------
*/

function verify_csrf_token(?string $token): bool
{
    if (
        empty($token) ||
        empty($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals(
        (string) $_SESSION['csrf_token'],
        $token
    );
}


/*
|--------------------------------------------------------------------------
| Regenerate token
|--------------------------------------------------------------------------
| Useful after successful authentication or other
| sensitive state changes.
|--------------------------------------------------------------------------
*/

function regenerate_csrf_token(): string
{
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));

    return $_SESSION['csrf_token'];
}


/*
|--------------------------------------------------------------------------
| HTML hidden input helper
|--------------------------------------------------------------------------
*/

function csrf_field(): string
{
    return
        '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(
            csrf_token(),
            ENT_QUOTES,
            'UTF-8'
        ) .
        '">';
}