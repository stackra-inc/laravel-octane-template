# Stackra Application Shell

Minimal, stateless, Octane-first application entry point for the Stackra
monorepo. This is a **deployment shell** — all business logic lives in modules.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Application Shell                             │
│                                                                     │
│  bootstrap/app.php    → Creates the Application instance            │
│  public/index.php     → HTTP entry (fallback, dev only)             │
│  public/frankenphp-worker.php → Octane worker (production)          │
│  artisan              → CLI entry (commands, workers, scheduler)     │
│  environments/        → Layered env files (.env, .secrets, .local)  │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                           Modules                                    │
│                                                                     │
│  stackra/laravel-framework       → Core framework extensions        │
│  stackra/laravel-infrastructure  → Database, ORM, Search, Octane    │
│  stackra/laravel-observability   → Logging, Monitoring, Audit       │
│  stackra/laravel-business        → Domain modules (Tenancy, etc.)   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## Design Principles

| Principle         | Implementation                                                            |
| ----------------- | ------------------------------------------------------------------------- |
| **Octane-first**  | FrankenPHP keeps the app in memory. Boot once, serve thousands.           |
| **Stateless**     | No local filesystem state. Redis for cache/sessions/queues, S3 for files. |
| **Module-driven** | Zero business logic in the shell. Modules auto-register everything.       |
| **Event-driven**  | Async jobs via Redis queues. Worker clusters scale independently.         |
| **Config-free**   | No `config/` directory. Modules provide defaults, env vars override.      |
| **12-Factor**     | All configuration via environment variables. One image, any role.         |

## Deployment Roles

The same Docker image serves all roles — only the entrypoint command changes:

| Role          | Command                                           | Purpose                      |
| ------------- | ------------------------------------------------- | ---------------------------- |
| **Web**       | `php artisan octane:start`                        | HTTP requests via FrankenPHP |
| **Worker**    | `php artisan queue:work --queue=high,default,low` | Process async jobs           |
| **Scheduler** | `php artisan schedule:run`                        | Cron-based task scheduling   |

## Quick Start

```bash
# 1. Install dependencies
composer install

# 2. Set up environment
composer setup:env

# 3. Start infrastructure (Postgres, Redis, MinIO, etc.)
composer docker:up

# 4. Generate app key + run migrations
php artisan key:generate
php artisan migrate

# 5. Start the dev server (Octane + file watcher)
composer dev:octane
```

## File Structure

```
applications/mngo/
├── bootstrap/
│   ├── app.php                    # Application bootstrap (5 lines of real code)
│   └── cache/                     # Compiled artifacts (gitignored)
├── environments/
│   ├── .env.example               # Template (committed)
│   ├── .env                       # Base config (gitignored)
│   ├── .env.secrets               # Decrypted secrets (gitignored, auto-generated)
│   └── .env.local                 # Developer overrides (gitignored, optional)
├── public/
│   ├── index.php                  # HTTP entry (php-fpm fallback)
│   └── frankenphp-worker.php      # Octane worker entry (production)
├── storage/                       # Runtime dirs (required by Laravel, mostly unused)
├── artisan                        # CLI entry point
├── frankenphp                     # FrankenPHP binary (gitignored)
├── composer.json                  # Dependencies + scripts
├── package.json                   # Turborepo integration
└── turbo.json                     # Workspace task overrides
```

## Environment Loading

The application loads environment files in layers (later overrides earlier):

```
environments/.env          → Base config (committed as .env.example)
environments/.env.secrets  → Decrypted secrets from ejson
environments/.env.local    → Developer overrides (optional)
```

This is handled automatically by the `LoadAdditionalEnvironmentFiles`
bootstrapper — no symlinks, no manual setup.

## Commands

```bash
# ── Development ───────────────────────────────────────────────────────────────
composer dev              # php artisan serve (basic)
composer dev:octane       # Octane + FrankenPHP + file watcher

# ── Environment ───────────────────────────────────────────────────────────────
composer env:dev          # Switch to development settings
composer env:prod         # Switch to production settings
composer env:status       # Show current environment

# ── Database ──────────────────────────────────────────────────────────────────
composer migrate          # Run pending migrations
composer migrate:fresh    # Drop all + re-migrate + seed
composer db:seed          # Run seeders

# ── Quality ───────────────────────────────────────────────────────────────────
composer lint             # Check code style (Pint)
composer analyse          # Static analysis (PHPStan)
composer test             # Run test suite (Pest)
composer quality          # All of the above

# ── Production ────────────────────────────────────────────────────────────────
composer octane:start:prod  # Cache config/routes/events + start Octane
composer octane:reload      # Zero-downtime worker reload
composer cache:warm         # Pre-warm all caches

# ── Docker ────────────────────────────────────────────────────────────────────
composer docker:up        # Start infrastructure services
composer docker:down      # Stop services
composer docker:build     # Build the application image
```

## Octane Architecture

This application runs **exclusively** on Laravel Octane with FrankenPHP. There
is no php-fpm fallback in production. The `public/index.php` exists only for
local development with `php artisan serve`.

### How Octane Works

```
Traditional PHP (php-fpm):
  Request → Boot Framework → Route → Controller → Response → Die
  Request → Boot Framework → Route → Controller → Response → Die
  (framework boots on EVERY request — ~50ms overhead)

Octane (FrankenPHP):
  Boot Framework (once)
  Request → Route → Controller → Response
  Request → Route → Controller → Response
  Request → Route → Controller → Response
  (framework stays in memory — ~0ms boot overhead)
```

### Lifecycle Hooks

Modules can hook into Octane's lifecycle via attributes:

```php
use Stackra\Octane\Attributes\OnRequestReceived;
use Stackra\Octane\Attributes\OnRequestTerminated;
use Stackra\Octane\Attributes\OnTickReceived;

// Reset state between requests (runs BEFORE each request)
#[OnRequestReceived(priority: 10)]
class ResetTenantContext
{
    public function handle(RequestReceived $event): void
    {
        // Clear tenant-specific state from the previous request
    }
}

// Cleanup after response sent (runs AFTER each request)
#[OnRequestTerminated(priority: 100)]
class FlushMetrics
{
    public function handle(RequestTerminated $event): void
    {
        // Flush accumulated metrics to the monitoring service
    }
}

// Periodic maintenance (runs every N milliseconds)
#[OnTickReceived]
class HealthPing
{
    public function handle(TickReceived $event): void
    {
        // Send heartbeat to monitoring
    }
}
```

### Octane Safety Rules

| Rule                                           | Why                             |
| ---------------------------------------------- | ------------------------------- |
| Never use mutable `static` properties          | They leak between requests      |
| Use `#[Scoped]` for request state              | Fresh instance per request      |
| Use `#[Singleton]` only for stateless services | Persists across ALL requests    |
| Close file handles explicitly                  | They persist in memory          |
| Don't store `Request` in singletons            | It changes every request        |
| Use Redis for sessions/cache                   | Not filesystem (shared workers) |

### Configuration

All Octane settings are in `config/octane.php` and controlled via env vars:

| Variable               | Default      | Description                     |
| ---------------------- | ------------ | ------------------------------- |
| `OCTANE_SERVER`        | `frankenphp` | Server driver                   |
| `OCTANE_WORKERS`       | `auto`       | Worker count (auto = CPU cores) |
| `OCTANE_TASK_WORKERS`  | `auto`       | Concurrent task workers         |
| `OCTANE_MAX_REQUESTS`  | `1000`       | Requests before worker recycle  |
| `OCTANE_TICK_INTERVAL` | `10000`      | Tick interval in ms             |
| `OCTANE_HTTPS`         | `false`      | Auto-HTTPS via Let's Encrypt    |

## Adding Modules

To add a new module to this application:

1. Add the module to `require` in `composer.json`:

   ```json
   "require": {
     "stackra/laravel-my-module": "@dev"
   }
   ```

2. Run `composer update`

3. The module's service provider auto-discovers and registers:
   - Routes
   - Migrations
   - Config defaults
   - Commands
   - Event listeners
   - Scheduled tasks

No manual registration needed. The compiler system handles discovery.

## Production Build (Docker)

```dockerfile
# =============================================================================
# Multi-stage production build
# =============================================================================
# Final image: FrankenPHP + compiled app, no dev dependencies.
# Same image serves all roles (web, worker, scheduler).
# =============================================================================

FROM dunglas/frankenphp:latest AS base

WORKDIR /app

# Install PHP extensions required by the application
RUN install-php-extensions \
    pcntl \
    pdo_pgsql \
    redis \
    intl \
    bcmath \
    opcache

# Copy application files
COPY . /app

# Install production dependencies only
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Compile and cache everything for maximum performance
RUN php artisan optimize \
    && php artisan event:cache \
    && php artisan di:compile

# ── Entry Points ──────────────────────────────────────────────────────────────
# Web (default): FrankenPHP worker mode
CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]

# Worker: Override CMD in docker-compose or K8s
# CMD ["php", "artisan", "queue:work", "--queue=high,default,low", "--tries=3", "--max-jobs=1000"]

# Scheduler: Override CMD in docker-compose or K8s
# CMD ["php", "artisan", "schedule:work"]
```

### Deployment Roles (same image, different command)

```yaml
# docker-compose.prod.yml
services:
  web:
    image: stackra/app:latest
    command: frankenphp run --config /app/Caddyfile
    deploy:
      replicas: 3

  worker:
    image: stackra/app:latest
    command:
      php artisan queue:work --queue=high,default,low --tries=3 --max-jobs=1000
    deploy:
      replicas: 5

  scheduler:
    image: stackra/app:latest
    command: php artisan schedule:work
    deploy:
      replicas: 1
```

## Template Usage

This application is designed to be used as a GitHub template. When creating a
new project:

1. Click "Use this template" on GitHub
2. Clone your new repository
3. Run `composer setup`
4. Add your domain modules to `composer.json`
5. Deploy

The shell never changes — only the modules you compose into it.
