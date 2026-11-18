#!/usr/bin/env bash
# Updates the official calculators (git submodules) to the latest upstream commit,
# rebuilds both images together (the Simples database is generated from the regime-regular
# flyway migrations, so they must always be rebuilt as a pair) and runs the smoke test.
#
# Usage: scripts/update-calculadoras.sh [--no-pull] [--no-smoke]
set -euo pipefail

cd "$(dirname "$0")/.."

PULL=1
SMOKE=1
for arg in "$@"; do
  case "$arg" in
    --no-pull)  PULL=0 ;;
    --no-smoke) SMOKE=0 ;;
    *) echo "Unknown option: $arg" >&2; exit 2 ;;
  esac
done

# The regime-regular repository has paths longer than 260 chars (Windows checkouts).
git config core.longpaths true
git submodule foreach --quiet 'git config core.longpaths true'

before=$(git submodule status)
if [ "$PULL" -eq 1 ]; then
  echo "==> Atualizando submodules"
  git submodule update --init --remote --recursive
else
  git submodule update --init --recursive
fi
after=$(git submodule status)

echo "==> Versões dos submodules"
echo "$after"
if [ "$before" != "$after" ]; then
  echo "==> Submodules mudaram. Lembre de commitar: git add services && git commit -m 'chore: update calculadoras'"
fi

echo "==> Reconstruindo imagens"
docker compose build --pull calc-regular calc-simples

echo "==> Subindo calculadoras"
docker compose up -d --wait calc-regular calc-simples

if [ "$SMOKE" -eq 1 ]; then
  echo "==> Teste de fumaça"
  docker compose --profile smoke run --rm --build smoke
fi
