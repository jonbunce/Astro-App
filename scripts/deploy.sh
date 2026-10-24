#!/usr/bin/env bash
# Deploy a plugin from server/ to the staging or live WordPress site on SiteGround.
#
#   scripts/deploy.sh <staging|live> <plugin-folder> [--dry-run] [--db-backup]
#
#   STAGING_WP   path to the staging site's public_html on the server (required for staging)
#
# Steps: PHP lint on the server (code is piped over stdin, nothing written), a backup of the
# plugin as it is on the server into ~/deploy-backups (outside the web root, so old PHP is never
# served as text), upload, unpack, then purge SiteGround's dynamic cache.

set -euo pipefail

TARGET="${1:-}"
PLUGIN="${2:-}"
shift 2 || true
DRY_RUN=0
DB_BACKUP=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --db-backup) DB_BACKUP=1 ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/server/$PLUGIN"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/live4}"
SSH_HOST="${SSH_HOST:-u17-o60grckj5ou9@ssh.wombourneallstars.co.uk}"
SSH=(ssh -i "$SSH_KEY" -p 18765 -o BatchMode=yes "$SSH_HOST")

case "$TARGET" in
  live) WP="www/wombourneallstars.co.uk/public_html" ;;
  staging) WP="${STAGING_WP:?Set STAGING_WP to the staging site public_html path}" ;;
  *) echo "Usage: $0 <staging|live> <plugin-folder> [--dry-run] [--db-backup]" >&2; exit 2 ;;
esac

[ -n "$PLUGIN" ] && [ -d "$SRC" ] || { echo "No plugin folder at $SRC" >&2; exit 2; }
if [ -n "$(git -C "$ROOT" status --porcelain -- "server/$PLUGIN")" ]; then
  echo "server/$PLUGIN has uncommitted changes. Commit first so the deploy matches git." >&2
  exit 1
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
REV="$(git -C "$ROOT" rev-parse --short HEAD)"
echo "Deploying $PLUGIN @ $REV to $TARGET ($WP)"

echo "Linting PHP on the server..."
fail=0
while IFS= read -r -d '' f; do
  if ! out="$("${SSH[@]}" 'php -l' < "$f" 2>&1)"; then
    echo "  ${f#"$ROOT/"}: $out"
    fail=1
  fi
done < <(find "$SRC" -name '*.php' -print0)
[ "$fail" -eq 0 ] || { echo "Lint failed; nothing deployed." >&2; exit 1; }
echo "  all PHP files OK"

if [ "$DRY_RUN" -eq 1 ]; then
  echo "Dry run: stopping before any change on the server."
  exit 0
fi

PLUGIN_DIR="$WP/wp-content/plugins/$PLUGIN"
"${SSH[@]}" "set -e; mkdir -p ~/deploy-backups ~/deploy-tmp
  if [ -d '$PLUGIN_DIR' ]; then tar czf ~/deploy-backups/$PLUGIN-$TARGET-$STAMP.tgz -C '$WP/wp-content/plugins' '$PLUGIN'; fi"
echo "Backed up server copy to ~/deploy-backups/$PLUGIN-$TARGET-$STAMP.tgz"

if [ "$DB_BACKUP" -eq 1 ]; then
  "${SSH[@]}" "cd '$WP' && wp db export ~/deploy-backups/db-$TARGET-$STAMP.sql --quiet && gzip ~/deploy-backups/db-$TARGET-$STAMP.sql"
  echo "Database exported to ~/deploy-backups/db-$TARGET-$STAMP.sql.gz (delete it once the deploy is verified)"
fi

tar czf - --exclude='*.bak*' --exclude='*.md' -C "$ROOT/server" "$PLUGIN" | "${SSH[@]}" "cat > ~/deploy-tmp/$PLUGIN-$STAMP.tgz"
"${SSH[@]}" "set -e; tar xzf ~/deploy-tmp/$PLUGIN-$STAMP.tgz -C '$WP/wp-content/plugins' && rm ~/deploy-tmp/$PLUGIN-$STAMP.tgz
  cd '$WP' && wp sg purge >/dev/null 2>&1 || true"
echo "Deployed $PLUGIN @ $REV to $TARGET and purged the cache."
echo "Roll back with: ssh ... \"tar xzf ~/deploy-backups/$PLUGIN-$TARGET-$STAMP.tgz -C '$WP/wp-content/plugins'\""
