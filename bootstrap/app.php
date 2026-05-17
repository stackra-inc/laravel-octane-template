<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Application Bootstrap
 * =============================================================================
 *
 * This is the single entry point that creates and configures the Laravel
 * application instance. The application is intentionally minimal — all
 * business logic, routes, middleware, and configuration live in modules.
 *
 * Architecture:
 *   - Octane-first: app boots once, stays in memory across requests
 *   - Stateless: no local filesystem state (Redis, S3, stdout)
 *   - Module-driven: modules auto-register providers, routes, commands
 *   - Event-driven: async jobs via Redis queues, worker clusters
 *
 * The Stackra Application class extends Laravel's base to provide:
 *   - Custom environment path (environments/ directory)
 *   - Layered env loading (.env → .env.secrets → .env.local)
 *   - Module path resolution for the monorepo
 *   - AOP interceptor support
 *   - Priority-based service provider registration
 *
 * @see \Stackra\Foundation\Application
 * @see \Stackra\Foundation\ApplicationBuilder
 */

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
// use Stackra\Foundation\Application;
use Illuminate\Foundation\Application;

$app = Application::configure(basePath: dirname(__DIR__))

    // ── Routing ──────────────────────────────────────────────────────────────
    // Only the health check endpoint is defined here. All application routes
    // are registered by modules via their service providers.
    ->withRouting(
        health: '/up',
    )

    // ── Middleware ────────────────────────────────────────────────────────────
    // Global middleware is registered by the foundation module. Application-
    // specific middleware can be appended here if needed.
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })

    // ── Exceptions ───────────────────────────────────────────────────────────
    // Exception handling is configured by the foundation module (Sentry,
    // structured logging, etc.). Custom handlers can be added here.
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })

    ->create();

// ── Environment Path Override ─────────────────────────────────────────────────
// Point Laravel to the environments/ directory for .env files.
// When Stackra\Foundation\Application is used, this is handled by the
// HasDirectories trait. This explicit call is the fallback for vanilla Laravel.
$app->useEnvironmentPath($app->basePath('environments'));

return $app;
