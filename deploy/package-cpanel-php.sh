#!/usr/bin/env bash
# Copy `site/` into dist-cpanel-php/ for cPanel (Apache + PHP). Excludes are in deploy/cpanel-site-excludes.txt.
#
# From project root:
#   npm run package:cpanel
#   bash deploy/package-cpanel-php.sh
set -euo pipefail

if ! command -v rsync >/dev/null 2>&1; then
  echo "rsync is required but not found. On macOS it is preinstalled; install rsync or use Git Bash/WSL on Windows." >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${ROOT}/dist-cpanel-php"
EXCLUDES="${ROOT}/deploy/cpanel-site-excludes.txt"

if [[ ! -f "${EXCLUDES}" ]]; then
  echo "Missing ${EXCLUDES}" >&2
  exit 1
fi

rm -rf "${OUT}"
mkdir -p "${OUT}"
rsync -a --delete --exclude-from="${EXCLUDES}" "${ROOT}/site/" "${OUT}/"

echo ""
echo "Packaged → ${OUT}"
echo "Upload the *contents* of that folder to your cPanel document root (often public_html/), or into a subdomain folder."
echo "Then in that directory (Terminal or SSH): composer install --no-dev --optimize-autoloader"
echo "Add a new .env there with MYSQL_* and API keys (do not copy a local .env blindly)."
echo ""
