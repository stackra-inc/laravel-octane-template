# Environment Configuration

This directory contains environment configuration files for the Laravel
application.

## Files

| File                  | Purpose                                       | Git     |
| --------------------- | --------------------------------------------- | ------- |
| `.env.example`        | Example application config (template)         | Tracked |
| `.env.docker.example` | Example Docker infrastructure config          | Tracked |
| `.env`                | Application configuration                     | Ignored |
| `.env.docker`         | Docker infrastructure configuration           | Ignored |
| `.env.secrets`        | Decrypted secrets from ejson (auto-generated) | Ignored |
| `.env.local`          | Developer overrides (optional)                | Ignored |

## How Environment Loading Works

The custom `Application` class loads env files in layers (later overrides
earlier):

1. `environments/.env` — base config (loaded by Laravel's
   `LoadEnvironmentVariables`)
2. `environments/.env.secrets` — decrypted secrets (loaded by
   `LoadAdditionalEnvironmentFiles`)
3. `environments/.env.local` — developer overrides (loaded by
   `LoadAdditionalEnvironmentFiles`)

**No symlinks required.** The `environmentPath()` override tells Laravel to look
in `environments/` directly.

## Setup

### Quick Setup

```bash
composer setup:env
```

This copies example files to create your environment:

1. `environments/.env.example` → `environments/.env`
2. `environments/.env.docker.example` → `environments/.env.docker`

### Secrets (ejson)

Sensitive values (API keys, passwords) are stored encrypted in `secrets.ejson`:

```bash
composer secrets:setup     # First-time: install ejson + store private key
composer secrets:decrypt   # Decrypt secrets.ejson → .env.secrets
composer secrets:encrypt   # Re-encrypt after editing secrets.ejson
```

### Developer Overrides

Create `environments/.env.local` to override any value locally without touching
`.env` or `.env.secrets`. This file is gitignored and never shared.

## File Purposes

### .env (Application Configuration)

Contains Laravel application settings:

- Database credentials (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)
- Cache/Redis credentials (REDIS_HOST, REDIS_PASSWORD)
- Mail settings (MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD)
- Application environment (APP_ENV, APP_DEBUG, APP_URL)
- Queue settings (QUEUE_CONNECTION)
- Search engine credentials (MEILISEARCH_KEY, ELASTICSEARCH_PASSWORD)
- Storage credentials (AWS, MINIO)
- Broadcasting settings (REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET)

### .env.secrets (Decrypted Secrets)

Auto-generated from `secrets.ejson`. Contains sensitive values that should never
be in `.env.example`:

- APP_KEY
- DB_PASSWORD
- API tokens and secret keys

### .env.docker (Docker Infrastructure)

Contains Docker container configuration:

- Container names and hostnames
- Port mappings
- Resource limits (CPU, memory)
- Network settings
- Build arguments (PHP_VERSION, NODE_VERSION)

## Security

- Never commit `.env`, `.env.secrets`, or `.env.local` to git
- Use `secrets.ejson` (encrypted) for sharing secrets via git
- Use different values for development, staging, and production
