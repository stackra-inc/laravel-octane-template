#!/usr/bin/env bash
# =============================================================================
# Setup EJSON — Install + Configure Private Keys
# =============================================================================
#
# One-time setup script for new team members. Handles:
#   1. Installing ejson (if not already installed)
#   2. Storing private keys in project-local .ejson-keys/
#   3. Running the first decrypt
#
# Works on macOS, Linux, and Windows (WSL/Git Bash).
#
# Usage:
#   ./scripts/ejson/setup.sh              # interactive
#   ./scripts/ejson/setup.sh <key>        # non-interactive (single key)
#   composer secrets:setup                # via composer script
# =============================================================================

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
KEYDIR="$ROOT/.ejson-keys"

# Public key (from secrets.ejson _public_key field)
MNGO_PUBLIC_KEY="61ef8376a22d4ae3d77bddc9713c78b621ec1208fd5c5fd95b8b909bc519e708"

echo ""
echo "🔐 EJSON Setup"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# ── Step 1: Install ejson if missing ─────────────────────────────────────────

if command -v ejson &>/dev/null; then
  echo "✓ ejson already installed ($(ejson version 2>/dev/null || echo 'unknown version'))"
else
  echo "⚠️  ejson not found — installing..."
  echo ""

  case "$(uname -s)" in
    Darwin)
      if command -v brew &>/dev/null; then
        echo "  → brew install shopify/shopify/ejson"
        brew install shopify/shopify/ejson
      else
        echo "  Homebrew not found. Install ejson manually:"
        echo "    brew install shopify/shopify/ejson"
        echo "    OR: go install github.com/Shopify/ejson/cmd/ejson@latest"
        exit 1
      fi
      ;;
    Linux)
      if command -v go &>/dev/null; then
        echo "  → go install github.com/Shopify/ejson/cmd/ejson@latest"
        go install github.com/Shopify/ejson/cmd/ejson@latest
      elif command -v brew &>/dev/null; then
        echo "  → brew install shopify/shopify/ejson"
        brew install shopify/shopify/ejson
      else
        echo "  Install ejson manually (requires Go):"
        echo "    go install github.com/Shopify/ejson/cmd/ejson@latest"
        echo ""
        echo "  Or download a prebuilt binary from:"
        echo "    https://github.com/Shopify/ejson/releases"
        exit 1
      fi
      ;;
    MINGW*|MSYS*|CYGWIN*)
      if command -v go &>/dev/null; then
        echo "  → go install github.com/Shopify/ejson/cmd/ejson@latest"
        go install github.com/Shopify/ejson/cmd/ejson@latest
      else
        echo "  Install ejson manually (requires Go):"
        echo "    go install github.com/Shopify/ejson/cmd/ejson@latest"
        echo ""
        echo "  Or download a prebuilt binary from:"
        echo "    https://github.com/Shopify/ejson/releases"
        echo "  and add it to your PATH."
        exit 1
      fi
      ;;
    *)
      echo "  Unknown OS: $(uname -s)"
      echo "  Install ejson manually: go install github.com/Shopify/ejson/cmd/ejson@latest"
      exit 1
      ;;
  esac

  if command -v ejson &>/dev/null; then
    echo ""
    echo "  ✓ ejson installed successfully"
  else
    echo ""
    echo "  ❌ ejson installation failed. Install manually and re-run this script."
    exit 1
  fi
fi

echo ""

# ── Step 2: Store private keys ───────────────────────────────────────────────

mkdir -p "$KEYDIR"

echo "Keys will be stored in: .ejson-keys/ (gitignored, never committed)"
echo ""

MNGO_PRIVATE_KEY="${1:-}"

if [ -z "$MNGO_PRIVATE_KEY" ]; then
  echo "Get the private key from a team lead (via 1Password, Slack DM, etc.)"
  echo ""
  echo "Public key: $MNGO_PUBLIC_KEY"
  echo ""
  read -rp "🔑 Private key: " MNGO_PRIVATE_KEY
fi

# Validate
if [ ${#MNGO_PRIVATE_KEY} -ne 64 ]; then
  echo ""
  echo "❌ Private key must be 64 hex characters (got ${#MNGO_PRIVATE_KEY})"
  exit 1
fi

# Write key
echo "$MNGO_PRIVATE_KEY" > "$KEYDIR/$MNGO_PUBLIC_KEY"

# Restrict permissions
chmod 600 "$KEYDIR/$MNGO_PUBLIC_KEY" 2>/dev/null || true

echo ""
echo "✓ Key saved: .ejson-keys/$MNGO_PUBLIC_KEY"

# ── Step 3: Decrypt ──────────────────────────────────────────────────────────

echo ""
echo "Decrypting secrets..."
export EJSON_KEYDIR="$KEYDIR"
bash "$ROOT/scripts/ejson/decrypt.sh"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Setup complete! You can now run: composer dev"
echo ""
