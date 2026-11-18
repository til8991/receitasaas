# Razonete — Reforma Tributária para escritórios de contabilidade

SaaS multi-escritório que mostra, para cada cliente do **Simples Nacional**, se compensa recolher o **IBS/CBS dentro do Simples** ou **pelo regime regular** (que gera crédito integral para os clientes B2B). O sistema faz simulação individual e em lote, importa XML de NF-e/NFC-e/NFS-e e gera relatório em PDF com a marca do escritório.

> **Simulação baseada na Calculadora oficial da Receita Federal. Não substitui análise profissional.**

Os tributos **não são calculados por nós**. Quem calcula são as calculadoras oficiais da Receita Federal (código aberto, MIT), que ficam como submodules em `services/` sem nenhuma alteração:

| Calculadora | Repositório | Serviço interno |
|---|---|---|
| Regime regular (IBS/CBS/IS) | `ReceitaFederal/calculadora-rtc-regime-regular` | `http://calc-regular:8080/api` |
| Simples Nacional | `ReceitaFederal/calculadora-rtc-simples-nacional` | `http://calc-simples:8082/api` |

As regras que **não** vêm das calculadoras (alíquota efetiva do Simples, repasse de crédito, mix de fornecedores etc.) são premissas parametrizáveis e estão documentadas em **[docs/premissas.md](docs/premissas.md)**. Leia antes de usar os resultados.

## Arquitetura

```
             VM (Nginx + TLS)
                    │ 127.0.0.1:8088
             ┌──────▼──────┐   rede "app"
             │    nginx    │── SPA React (build estático)
             └──────┬──────┘
               /api │ FastCGI
             ┌──────▼──────┐   ┌─────────┐  ┌───────┐
             │  php-fpm    │──▶│ MySQL 8 │  │ Redis │◀── horizon (filas) / scheduler
             └──────┬──────┘   └─────────┘  └───────┘
                    │ rede "calc" (internal: sem internet e sem porta pública)
          ┌─────────┴──────────┐
   ┌──────▼───────┐    ┌───────▼───────┐
   │ calc-regular │    │ calc-simples  │   Java 21, perfil offline, SQLite somente leitura
   └──────────────┘    └───────────────┘
```

| Pasta | Conteúdo |
|---|---|
| `backend/` | Laravel 12: API REST (Sanctum), filas (Horizon), Pest |
| `frontend/` | React + Vite + TypeScript + Tailwind (SPA) |
| `services/` | Calculadoras oficiais (git submodules, nunca editadas) |
| `docker/` | Dockerfiles: calculadoras (multi-stage com geração do SQLite), php, nginx |
| `scripts/` | `deploy.sh`, `update-calculadoras.sh`, `smoke-test.sh` |
| `docs/` | Contratos das calculadoras, premissas e deploy |

Pontos principais do backend:
- `app/Services/Calculadora/`: `RegimeGeralClient` e `SimplesNacionalClient`, com timeout, retry, log no canal `calculadora` e cache Redis dos dados abertos. DTOs feitos a partir dos contratos reais (ver [docs/calculadoras.md](docs/calculadoras.md)).
- `app/Services/Comparador/`: `ComparadorService` (os dois cenários e a recomendação), `SimplesAliquotaService` (premissa P-01) e `Premissas`.
- `app/Services/Invoices/`: parser de XML (NF-e, NFC-e, NFS-e Nacional e ABRASF, protegido contra XXE) e importação.
- Jobs: `ImportInvoicesJob`, `RunSimulationJob`, `RunBatchJob`, `GenerateReportPdfJob`.
- Auditoria: cada simulação grava o payload enviado, a resposta bruta, a versão de cada calculadora (`/versao`) e a data.
- Multi-tenant por escritório: global scope + middleware `SetTenant`. Limite de CNPJs ativos por plano (`config/plans.php`), atrás da interface `BillingGateway`.

## Rodando localmente

Requisitos: PHP 8.3 (`pdo_sqlite`, `zip`, `gd`, `bcmath`, `dom`), Composer, Node 22+, Docker (para as calculadoras).

```bash
git clone --recurse-submodules https://github.com/til8991/receitasaas.git
cd receitasaas && git config core.longpaths true   # o repo do regime regular tem caminhos longos (Windows)

# Calculadoras oficiais (gera os bancos SQLite no build; a 1ª vez demora)
docker compose up -d --wait calc-regular calc-simples
docker compose --profile smoke run --rm --build smoke
cp docker-compose.dev.example.yml docker-compose.override.yml   # expõe as calculadoras em 127.0.0.1:18080/18082
docker compose up -d calc-regular calc-simples

# API
cd backend
composer install
cp .env.example .env && php artisan key:generate
# no .env: CALC_REGULAR_URL=http://127.0.0.1:18080/api  CALC_SIMPLES_URL=http://127.0.0.1:18082/api  QUEUE_CONNECTION=sync
php artisan migrate && php artisan storage:link
php artisan serve                       # http://localhost:8000

# SPA
cd ../frontend && npm install && npm run dev   # http://localhost:5173 (proxy de /api para :8000)
```

## Testes

```bash
cd backend
php artisan test                         # Pest: unitários + feature (calculadoras simuladas com Http::fake)

# Integração contra as calculadoras reais (opcional)
CALC_INTEGRATION_TESTS=true CALC_REGULAR_URL=http://127.0.0.1:18080/api \
CALC_SIMPLES_URL=http://127.0.0.1:18082/api php artisan test --testsuite=Integration

cd ../frontend && npx tsc -b && npx eslint src && npm run build
```

## Atualizar as calculadoras oficiais

```bash
scripts/update-calculadoras.sh   # atualiza os submodules, reconstrói as duas imagens juntas e roda o teste de fumaça
```

## Deploy

Veja **[docs/deploy.md](docs/deploy.md)**: VM com Nginx (TLS) na frente do `docker compose`.
