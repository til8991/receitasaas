#!/bin/sh
# Smoke test for the two official calculators. Runs INSIDE the compose network:
#   docker compose --profile smoke run --rm smoke
# Requires curl + jq (provided by docker/smoke/Dockerfile).
set -eu

REGULAR="${CALC_REGULAR_URL:-http://calc-regular:8080/api}"
SIMPLES="${CALC_SIMPLES_URL:-http://calc-simples:8082/api}"
PAYLOADS="${PAYLOADS_DIR:-/smoke/payloads}"
FAILS=0

ok()   { echo "  [OK]   $*"; }
warn() { echo "  [WARN] $*"; }
fail() { echo "  [FAIL] $*"; FAILS=$((FAILS + 1)); }

# check <description> <jq expression returning true/false> <json>
check() {
  if printf '%s' "$3" | jq -e "$2" >/dev/null 2>&1; then ok "$1"; else fail "$1"; fi
}

# post <url> <file> -> sets BODY and STATUS
post() {
  RESP=$(curl -sS -w '\n%{http_code}' -X POST "$1" -H 'Content-Type: application/json' --data-binary "@$2")
  STATUS=$(printf '%s' "$RESP" | tail -n1)
  BODY=$(printf '%s' "$RESP" | sed '$d')
}

echo "== Versões"
V_REG=$(curl -fsS "$REGULAR/calculadora/dados-abertos/versao")
V_SN=$(curl -fsS "$SIMPLES/versao")
echo "  regime-regular : $(printf '%s' "$V_REG" | jq -c '{versaoApp, versaoDb, dataVersaoDb, ambiente}')"
echo "  simples        : $(printf '%s' "$V_SN"  | jq -c '{versaoApp, versaoDb, dataVersaoDb, ambiente}')"
DB_REG=$(printf '%s' "$V_REG" | jq -r '.versaoDb')
DB_SN=$(printf '%s' "$V_SN" | jq -r '.versaoDb')
if [ "$DB_REG" = "$DB_SN" ]; then ok "mesma versão de banco nas duas calculadoras ($DB_REG)"
else fail "versões de banco diferentes: regular=$DB_REG simples=$DB_SN"; fi

echo "== Regime regular: POST /calculadora/observabilidade/regime-geral (exemplo de docs/exemplos.md)"
post "$REGULAR/calculadora/observabilidade/regime-geral" "$PAYLOADS/regime-geral.json"
[ "$STATUS" = "200" ] && ok "HTTP 200" || fail "HTTP $STATUS: $BODY"
check "4 objetos retornados"                         '.objetos | length == 4' "$BODY"
check "itens 1-3 em CALCULO_SIMULADO"                '[.objetos[0:3][].estadoItem] | all(. == "CALCULO_SIMULADO")' "$BODY"
check "item 4 em INCONSISTENCIA_ENTRADA com REG-011" '.objetos[3].estadoItem == "INCONSISTENCIA_ENTRADA" and .objetos[3].erros[0].code == "REG-011"' "$BODY"
check "item 3: vCBS = 42.24 (redução de 60%)"        '.objetos[2].tribCalc.IBSCBS.gIBSCBS.gCBS.vCBS == "42.24"' "$BODY"
TOT=$(printf '%s' "$BODY" | jq -c '.total.tribCalc.IBSCBSTot | {vBCIBSCBS, vIBS: .gIBS.vIBS, vCBS: .gCBS.vCBS}')
if [ "$TOT" = '{"vBCIBSCBS":"1465.00","vIBS":"0.64","vCBS":"56.76"}' ]; then ok "totais iguais aos documentados $TOT"
else warn "totais diferentes dos documentados (possível mudança de versão): $TOT"; fi

echo "== Simples Nacional: POST /calculadora/observabilidade/simples-nacional (payload montado a partir dos exemplos do Swagger)"
post "$SIMPLES/calculadora/observabilidade/simples-nacional" "$PAYLOADS/simples-nacional.json"
[ "$STATUS" = "200" ] && ok "HTTP 200" || fail "HTTP $STATUS: $BODY"
check "1 objeto retornado"                  '.objetos | length == 1' "$BODY"
check "item 1 em CALCULADO"                 '.objetos[0].estadoItem == "CALCULADO"' "$BODY"
check "total.totalTributos presente"        '.total.totalTributos != null' "$BODY"
echo "  item 1 : $(printf '%s' "$BODY" | jq -c '.objetos[0]')"
echo "  total  : $(printf '%s' "$BODY" | jq -c '.total')"

echo
if [ "$FAILS" -eq 0 ]; then echo "SMOKE TEST OK"; else echo "SMOKE TEST FALHOU ($FAILS verificações)"; exit 1; fi
