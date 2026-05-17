<?php

declare(strict_types=1);

/**
 * =============================================================================
 * FrankenPHP / Octane Worker Entry Point
 * =============================================================================
 *
 * This is the primary entry point in production. FrankenPHP keeps the
 * application booted in memory and handles requests without re-bootstrapping
 * on every request (unlike traditional php-fpm).
 *
 * Performance characteristics:
 *   - Boot time: ~0ms (app already in memory)
 *   - Memory: shared across requests (singletons persist)
 *   - Throughput: 10-50x faster than php-fpm for typical workloads
 *
 * Important:
 *   - Singletons survive across requests — never store request state in them
 *   - Use #[Scoped] for request-specific services
 *   - Static mutable properties leak between requests
 *
 * @see https://frankenphp.dev/docs/worker/
 * @see https://laravel.com/docs/octane
 */

// ── Path Configuration ───────────────────────────────────────────────────────
// These are used by Octane's worker script to locate the application.
// In Docker, these resolve to /var/www/html and /var/www/html/public.
$_SERVER['APP_BASE_PATH'] = $_ENV['APP_BASE_PATH'] ?? $_SERVER['APP_BASE_PATH'] ?? __DIR__ . '/..';
$_SERVER['APP_PUBLIC_PATH'] = $_ENV['APP_PUBLIC_PATH'] ?? $_SERVER['APP_PUBLIC_PATH'] ?? __DIR__;

// ── Delegate to Octane ───────────────────────────────────────────────────────
// Octane's worker handles the request loop, warm-up, and graceful shutdown.
require __DIR__ . '/../vendor/laravel/octane/bin/frankenphp-worker.php';
