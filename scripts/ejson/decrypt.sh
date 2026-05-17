#!/usr/bin/env bash
# =============================================================================
# Decrypt secrets.ejson → .env.secrets
# =============================================================================
#
# Decrypts all secrets.ejson files in the monorepo into .env.secrets files:
#   - applications/mngo/environments/secrets.ejson → .env.secrets
#
# Secrets are kept separate from plain config (.env). Laravel loads them
# via symlink or direct reference.
#
# Usage:
#   ./scripts/ejson/decrypt.sh           # decrypt all
#   ./scripts/ejson/decrypt.sh mngo      # decrypt mngo only
#   composer secrets:decrypt             # same as above
#
# Prerequisites:
#   - ejson installed (brew install shopify/shopify/ejson)
#   - Private key in .ejson-keys/ (or EJSON_KEYDIR)
# =============================================================================

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TARGET="${1:-all}"

# ── Resolve EJSON_KEYDIR ─────────────────────────────────────────────────────
# Priority:
#   1. EJSON_KEYDIR env var (if already set)
#   2. Project-local .ejson-keys/ (cross-platform, recommended)
#   3. /opt/homebrew/etc/ejson/keys (macOS Homebrew Apple Silicon)
#   4. /usr/local/etc/ejson/keys (macOS Homebrew Intel)
#   5. /opt/ejson/keys (ejson default on Linux)

if [ -z "${EJSON_KEYDIR:-}" ]; then
  if [ -d "$ROOT/.ejson-keys" ]; then
    export EJSON_KEYDIR="$ROOT/.ejson-keys"
  elif [ -d "/opt/homebrew/etc/ejson/keys" ]; then
    export EJSON_KEYDIR="/opt/homebrew/etc/ejson/keys"
  elif [ -d "/usr/local/etc/ejson/keys" ]; then
    export EJSON_KEYDIR="/usr/local/etc/ejson/keys"
  elif [ -d "/opt/ejson/keys" ]; then
    export EJSON_KEYDIR="/opt/ejson/keys"
  fi
fi

# ── Validate prerequisites ───────────────────────────────────────────────────

if ! command -v ejson &>/dev/null; then
  echo "❌ ejson not found. Install with: brew install shopify/shopify/ejson"
  exit 1
fi

# ── Decrypt root-level secrets ────────────────────────────────────────────────

decrypt_root() {
  local secrets_file="$ROOT/environments/secrets.ejson"
  local env_file="$ROOT/environments/.env.secrets"

  if [ ! -f "$secrets_file" ]; then
    echo "⚠️  root: environments/secrets.ejson not found — skipping"
    return 0
  fi

  {
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
    echo "# 🔐 Secrets — Auto-generated from secrets.ejson"
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
    echo "#"
    echo "# DO NOT edit manually. DO NOT commit this file."
    echo "# Regenerate with: composer secrets:decrypt"
    echo "# Last decrypted: $(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    echo "#"
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
    echo ""
  } > "$env_file"

  ejson decrypt "$secrets_file" | python3 -c "
import json, sys
data = json.load(sys.stdin)
for key, value in sorted(data.items()):
    if key.startswith('_'):
        continue
    print(f'{key}={value}')
" >> "$env_file"

  local var_count
  var_count=$(grep -c '=' "$env_file" || echo "0")
  echo "✓ root: decrypted → environments/.env.secrets ($var_count variables)"
}

# ── Decrypt application secrets ──────────────────────────────────────────────

decrypt_app() {
  local app_name="$1"
  local env_dir="$ROOT/applications/$app_name/environments"
  local secrets_file="$env_dir/secrets.ejson"
  local env_file="$env_dir/.env.secrets"

  if [ ! -f "$secrets_file" ]; then
    echo "⚠️  $app_name: environments/secrets.ejson not found — skipping"
    return 0
  fi

  {
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
    echo "# 🔐 Secrets — Auto-generated from secrets.ejson"
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
    echo "#"
    echo "# DO NOT edit manually. DO NOT commit this file."
    echo "# Regenerate with: composer secrets:decrypt"
    echo "# Last decrypted: $(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    echo "#"
    echo "# ═══════════════════════════════════════════════════════════════════════════════"
    echo ""
  } > "$env_file"

  ejson decrypt "$secrets_file" | python3 -c "
import json, sys
data = json.load(sys.stdin)
for key, value in sorted(data.items()):
    if key.startswith('_'):
        continue
    print(f'{key}={value}')
" >> "$env_file"

  local var_count
  var_count=$(grep -c '=' "$env_file" || echo "0")
  echo "✓ $app_name: decrypted → environments/.env.secrets ($var_count variables)"
}

# ── Execute ──────────────────────────────────────────────────────────────────

echo ""
echo "🔐 Decrypting secrets..."
echo ""

if [ "$TARGET" = "all" ]; then
  # 1. Root-level secrets (environments/secrets.ejson)
  decrypt_root

  # 2. Auto-discover all applications with secrets.ejson
  for secrets_file in "$ROOT"/applications/*/environments/secrets.ejson; do
    if [ -f "$secrets_file" ]; then
      app_name=$(basename "$(dirname "$(dirname "$secrets_file")")")
      decrypt_app "$app_name"
    fi
  done
elif [ "$TARGET" = "root" ]; then
  decrypt_root
elif [ "$TARGET" = "mngo" ] || [ "$TARGET" = "app" ]; then
  decrypt_app "mngo"
else
  # Treat target as an application name
  decrypt_app "$TARGET"
fi

echo ""
echo "✔ Done."
