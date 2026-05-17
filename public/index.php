<?php

declare(strict_types=1);

/**
 * =============================================================================
 * HTTP Entry Point
 * =============================================================================
 *
 * This file serves as the front controller for all HTTP requests when running
 * without Octane (e.g. php artisan serve, nginx + php-fpm, Apache).
 *
 * In production, Octane/FrankenPHP handles requests via the worker entry
 * (public/frankenphp-worker.php) and this file is only used as a fallback.
 *
 * Request lifecycle:
 *   1. Define start time for performance monitoring
 *   2. Check maintenance mode (short-circuit if active)
 *   3. Load Composer autoloader
 *   4. Bootstrap the application
 *   5. Handle the request and send the response
 */

use Illuminate\Http\Request;

// ── Performance Monitoring ───────────────────────────────────────────────────
// Available globally via LARAVEL_START for measuring boot time.
define('LARAVEL_START', microtime(true));

// ── Maintenance Mode ─────────────────────────────────────────────────────────
// When the app is in maintenance mode, this pre-rendered file is served
// directly without booting the framework (zero overhead).
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// ── Autoloader ───────────────────────────────────────────────────────────────
require __DIR__ . '/../vendor/autoload.php';

// ── Handle Request ───────────────────────────────────────────────────────────
// Bootstrap the application and dispatch the HTTP request through the
// middleware pipeline, router, and controller stack.
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->handleRequest(Request::capture());
