<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Octane Configuration
 * =============================================================================
 *
 * This application runs exclusively on Laravel Octane with FrankenPHP.
 * Octane keeps the application booted in memory, eliminating the bootstrap
 * cost on every request (10-50x faster than traditional php-fpm).
 *
 * Architecture:
 *   ┌─────────────────────────────────────────────────────────────────┐
 *   │                    FrankenPHP Process                            │
 *   │                                                                 │
 *   │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐          │
 *   │  │ Worker 1 │  │ Worker 2 │  │ Worker N │  │  Tick   │          │
 *   │  │ (HTTP)   │  │ (HTTP)   │  │ (HTTP)   │  │ (Cron)  │          │
 *   │  └─────────┘  └─────────┘  └─────────┘  └─────────┘          │
 *   │       │              │              │           │               │
 *   │       └──────────────┴──────────────┴───────────┘               │
 *   │                         │                                       │
 *   │              Shared Application Instance                        │
 *   │              (Singletons persist across requests)               │
 *   └─────────────────────────────────────────────────────────────────┘
 *
 * Key behaviors:
 *   - Singletons (#[Singleton]) survive across ALL requests
 *   - Scoped services (#[Scoped]) are fresh per request
 *   - Static properties persist — never use mutable statics
 *   - Database connections are recycled between requests
 *   - File handles and streams persist — close them explicitly
 *
 * @see https://laravel.com/docs/octane
 * @see https://frankenphp.dev/docs/worker/
 */

use Laravel\Octane\Contracts\OperationTerminated;
use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\TaskTerminated;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Events\TickTerminated;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Events\WorkerStopping;
use Laravel\Octane\Listeners\CloseMonologHandlers;
use Laravel\Octane\Listeners\EnsureUploadedFilesAreValid;
use Laravel\Octane\Listeners\EnsureUploadedFilesCanBeMoved;
use Laravel\Octane\Listeners\FlushOnce;
use Laravel\Octane\Listeners\FlushTemporaryContainerInstances;
use Laravel\Octane\Listeners\ReportException;
use Laravel\Octane\Listeners\StopWorkerIfNecessary;
use Laravel\Octane\Octane;

return [

    /*
    |--------------------------------------------------------------------------
    | Octane Server
    |--------------------------------------------------------------------------
    |
    | FrankenPHP is the default and recommended server. It's a single binary
    | that handles HTTP/2, HTTP/3, automatic HTTPS, and worker mode — no
    | separate web server (nginx/Apache) needed.
    |
    | Supported: "frankenphp", "swoole", "roadrunner"
    |
    */

    'server' => env('OCTANE_SERVER', 'frankenphp'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Configuration
    |--------------------------------------------------------------------------
    |
    | FrankenPHP can automatically provision and renew TLS certificates via
    | Let's Encrypt. In production behind a load balancer, set to false and
    | let the LB handle TLS termination.
    |
    */

    'https' => env('OCTANE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Worker Count
    |--------------------------------------------------------------------------
    |
    | Number of worker processes. Each worker handles one request at a time.
    | Set to "auto" to use the number of CPU cores, or specify a fixed number.
    |
    | Guidelines:
    |   - CPU-bound workloads: workers = CPU cores
    |   - IO-bound workloads: workers = CPU cores * 2
    |   - Memory-constrained: reduce workers, increase max_requests
    |
    */

    'workers' => env('OCTANE_WORKERS', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Task Workers
    |--------------------------------------------------------------------------
    |
    | Number of workers dedicated to concurrent tasks (Octane::concurrently).
    | These handle parallel operations within a single request.
    |
    */

    'task_workers' => env('OCTANE_TASK_WORKERS', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Max Requests
    |--------------------------------------------------------------------------
    |
    | Maximum number of requests a worker handles before being recycled.
    | This prevents memory leaks from accumulating over time.
    |
    | Set to 0 for unlimited (not recommended in production).
    |
    */

    'max_requests' => (int) env('OCTANE_MAX_REQUESTS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Tick Interval
    |--------------------------------------------------------------------------
    |
    | Interval in milliseconds between tick events. Tick handlers run periodic
    | maintenance tasks (health checks, metric flushing, cache cleanup).
    |
    | Set to 0 to disable ticks entirely.
    |
    */

    'tick_interval' => (int) env('OCTANE_TICK_INTERVAL', 10000),

    /*
    |--------------------------------------------------------------------------
    | Warm Services
    |--------------------------------------------------------------------------
    |
    | Services to resolve and cache when the application boots. These are
    | pre-warmed in memory so the first request doesn't pay the resolution cost.
    |
    | Add any service that is expensive to construct (database connections,
    | HTTP clients, cache stores, etc.).
    |
    */

    'warm' => [
        ...Octane::defaultServicesToWarm(),
        'cache',
        'cache.store',
        'config',
        'db',
        'db.factory',
        'hash',
        'log',
        'router',
        'routes',
        'translator',
        'url',
        'validator',
        'view',
    ],

    /*
    |--------------------------------------------------------------------------
    | Flush Services
    |--------------------------------------------------------------------------
    |
    | Services to flush (forget) between requests. This ensures request-specific
    | state doesn't leak between requests. The container will re-resolve these
    | fresh on the next request.
    |
    | Only add services that hold request-specific state (auth, session, etc.).
    | Do NOT flush expensive-to-construct services (db, cache) — they're safe
    | to reuse across requests.
    |
    */

    'flush' => [
        'auth',
        'auth.driver',
        'cookie',
        'session',
        'session.store',
    ],

    /*
    |--------------------------------------------------------------------------
    | Garbage Collection
    |--------------------------------------------------------------------------
    |
    | Frequency of garbage collection (every N requests per worker).
    | Lower values = more frequent GC = lower memory but slightly more CPU.
    |
    | Set to null to use PHP's default GC behavior.
    |
    */

    'garbage' => (int) env('OCTANE_GARBAGE_COLLECTION', 50),

    /*
    |--------------------------------------------------------------------------
    | Cache Table (Swoole only)
    |--------------------------------------------------------------------------
    |
    | Size of the in-memory cache table for Swoole. Not used with FrankenPHP.
    |
    */

    'cache' => [
        'rows' => 1000,
        'bytes' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables (Swoole only)
    |--------------------------------------------------------------------------
    |
    | Shared memory tables for Swoole. Not used with FrankenPHP.
    |
    */

    'tables' => [
        'example:1000' => [
            'name' => 'string:1000',
            'votes' => 'int',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watching (Development Only)
    |--------------------------------------------------------------------------
    |
    | Directories to watch for file changes in development. When a file changes,
    | Octane automatically reloads workers. Requires `--watch` flag.
    |
    | In production, this is ignored (no --watch flag).
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config',
        'database',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        '.env',
    ],

    'listeners' => [
        WorkerStarting::class => [
            EnsureUploadedFilesAreValid::class,
            EnsureUploadedFilesCanBeMoved::class,
        ],

        RequestReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            ...Octane::prepareApplicationForNextRequest(),
            //
        ],

        RequestHandled::class => [
            //
        ],

        RequestTerminated::class => [
            // FlushUploadedFiles::class,
        ],

        TaskReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            //
        ],

        TaskTerminated::class => [
            //
        ],

        TickReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            //
        ],

        TickTerminated::class => [
            //
        ],

        OperationTerminated::class => [
            FlushOnce::class,
            FlushTemporaryContainerInstances::class,
            // DisconnectFromDatabases::class,
            // CollectGarbage::class,
        ],

        WorkerErrorOccurred::class => [
            ReportException::class,
            StopWorkerIfNecessary::class,
        ],

        WorkerStopping::class => [
            CloseMonologHandlers::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Execution Time
    |--------------------------------------------------------------------------
    |
    | The following setting configures the maximum execution time for requests
    | being handled by Octane. You may set this value to 0 to indicate that
    | there isn't a specific time limit on Octane request execution time.
    |
    */

    'max_execution_time' => 30,
];
