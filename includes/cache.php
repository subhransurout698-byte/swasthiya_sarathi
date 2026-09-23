<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Swasthya Saarthi - Cache & Security Headers
|--------------------------------------------------------------------------
| IMPORTANT:
| Patient records, triage reports and authenticated pages should NOT
| be browser-cached.
|--------------------------------------------------------------------------
*/


/**
 * Disable browser/proxy caching.
 *
 * Use this on:
 * - dashboard.php
 * - patients.php
 * - queue.php
 * - review.php
 * - new_triage.php
 */
function no_cache(): void
{
    if (headers_sent()) {
        return;
    }

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
    );

    header(
        'Cache-Control: post-check=0, pre-check=0',
        false
    );

    header('Pragma: no-cache');

    header('Expires: 0');
}


/**
 * Cache public/static content for a short period.
 *
 * DO NOT use this for pages containing patient information.
 */
function public_cache(int $seconds = 3600): void
{
    if (headers_sent()) {
        return;
    }

    $seconds = max(0, $seconds);

    header(
        'Cache-Control: public, max-age=' . $seconds
    );

    header(
        'Expires: ' .
        gmdate(
            'D, d M Y H:i:s',
            time() + $seconds
        ) .
        ' GMT'
    );
}


/**
 * Prevent sensitive pages from being stored by browsers,
 * shared proxies or back/forward caches.
 */
function sensitive_page_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header(
        'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private'
    );

    header(
        'Pragma: no-cache'
    );

    header(
        'Expires: 0'
    );

    header(
        'Vary: Cookie'
    );
}


/**
 * Add useful security headers.
 *
 * These are suitable as a starting point for the portal.
 */
function security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header(
        'X-Content-Type-Options: nosniff'
    );

    header(
        'X-Frame-Options: SAMEORIGIN'
    );

    header(
        'Referrer-Policy: strict-origin-when-cross-origin'
    );

    header(
        'Permissions-Policy: camera=(), microphone=(self), geolocation=()'
    );
}


/**
 * Enable the recommended headers for an authenticated page.
 */
function secure_authenticated_page(): void
{
    sensitive_page_headers();
    security_headers();
}


/**
 * Cache-control value for static assets when you generate
 * versioned filenames such as:
 *
 * style.css?v=20260918
 */
function asset_cache_headers(
    int $seconds = 604800
): void
{
    if (headers_sent()) {
        return;
    }

    $seconds = max(0, $seconds);

    header(
        'Cache-Control: public, max-age=' . $seconds
    );
}