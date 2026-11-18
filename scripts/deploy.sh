#!/usr/bin/env bash
# Deploy/update on the VM (run from the repository root):
#   scripts/deploy.sh            # pull code, rebuild app images, migrate, restart
#   scripts/deploy.sh --calc     # also update and rebuild the official calculators
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || { echo "Crie o .env a partir de .env.production.example" >&2; exit 1; }
for var in APP_KEY APP_URL DB_PASSWORD DB_ROOT_PASSWORD REDIS_PASSWORD; do
  grep -Eq "^${var}=.+" .env || { echo "Defina ${var} no .env" >&2; exit 1; }
done
set -a; . ./.env; set +a

UPDATE_CALC=0
[ "${1:-}" = "--calc" ] && UPDATE_CALC=1

echo "==> Atualizando código"
git pull --ff-only
git config core.longpaths true
git submodule update --init --recursive

if [ "$UPDATE_CALC" -eq 1 ]; then
  scripts/update-calculadoras.sh --no-smoke
fi

echo "==> Construindo imagens"
docker compose build php nginx

echo "==> Subindo banco, cache e calculadoras"
docker compose up -d --wait mysql redis calc-regular calc-simples

echo "==> Migrations"
docker compose run --rm php php artisan migrate --force

echo "==> Subindo aplicação"
docker compose up -d --wait php nginx
docker compose up -d horizon scheduler
docker compose exec -T horizon php artisan horizon:terminate || true

echo "==> Teste de fumaça das calculadoras"
docker compose --profile smoke run --rm --build smoke

echo "==> Saúde"
curl -fsS "http://127.0.0.1:${APP_PORT:-8088}/healthz" && curl -fsS -o /dev/null -w 'API /up: %{http_code}\n' "http://127.0.0.1:${APP_PORT:-8088}/up"
docker compose ps
