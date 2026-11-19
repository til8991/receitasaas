# Instruções para quem continuar este projeto

SaaS "Razonete" para escritórios de contabilidade: compara, por cliente do Simples Nacional, recolher IBS/CBS
**dentro do DAS** × **pelo regime regular** (gerando crédito integral para clientes PJ), com painel multi-clientes,
lotes e relatório PDF com a marca do escritório. Estado em 2026-11-19: fases 1–6 do plano original + landing page
concluídas, commitadas e no GitHub (`til8991/receitasaas`, branch `main`). CI (GitHub Actions) verde.

Leia antes de mexer: `README.md`, `docs/calculadoras.md`, `docs/premissas.md`, `docs/deploy.md`.

## Regras do projeto (definidas pelo dono — não mudar)

- **Nenhum cálculo de tributo é nosso.** Os cálculos vêm das calculadoras oficiais da Receita Federal (submódulos em
  `services/`, licença MIT). **Nunca modificar o código dos submódulos.** Integração só pelos endpoints documentados
  (README, `docs/exemplos.md`, Swagger); não inventar payloads.
- Toda simulação guarda payload, resposta bruta, versão da calculadora (`/versao`) e data.
- Regra de negócio que não vem da calculadora é parametrizável e documentada em `docs/premissas.md` (P-01..P-11).
  **Avisar o dono sempre que precisar assumir algo da legislação** — não decidir sozinho.
- Aviso fixo em toda tela/PDF: "Simulação baseada na Calculadora oficial da Receita Federal. Não substitui análise profissional."
- Código em inglês, interface em pt-BR.

## Mapa rápido

- `backend/` Laravel 12 (Sanctum, Pest, Horizon, DomPDF). Integração: `app/Services/Calculadora/*`;
  comparação: `app/Services/Comparador/ComparadorService.php`; premissas: `config/premissas.php`,
  `config/simples_nacional.php`; planos: `config/plans.php`; multi-tenant: `BelongsToOffice` + `App\Support\Tenant`.
- `frontend/` React + Vite + TS + Tailwind v4. Landing pública em `src/pages/Landing.tsx` (servida em `/` para
  visitantes, ver `Protected` em `src/App.tsx`).
- `docker/`, `docker-compose.yml`, `scripts/deploy.sh`, `deploy/nginx-host.conf` — produção em VM.
- Testes: `cd backend && php artisan test` (59 passam, 3 de integração pulados sem `CALC_INTEGRATION_TESTS=true`);
  frontend: `npx tsc -b && npx eslint src && npm run build`.

## O que FALTA (em ordem de prioridade)

### 1. Validar com Docker (bloqueado na máquina do dono)
O Docker Desktop quebrou depois que o disco C: lotou. **Nunca foi validado:** build das imagens php/nginx/calc-*,
as calculadoras oficiais reais rodando e o teste de fumaça. Quando o Docker funcionar:
```
docker compose build
docker compose up -d --wait calc-regular calc-simples
docker compose --profile smoke run --rm --build smoke
cd backend && CALC_INTEGRATION_TESTS=true php artisan test tests/Integration
```
Corrigir o que falhar (provavelmente detalhes de Dockerfile/caminhos). O `backend/.env` local aponta
`CALC_REGULAR_URL`/`CALC_SIMPLES_URL` para um mock de desenvolvimento em `127.0.0.1:18089` que **não está no repo**;
trocar para as calculadoras reais (`docker-compose.dev.example.yml` expõe 18080/18082 no localhost).

### 2. Validação contábil das premissas (depende de contador — não resolver sozinho)
Listar para o dono e pedir confirmação de um contador:
- **P-01** tabelas de alíquotas efetivas da LC 123 (Anexos I–V) transcritas em `config/simples_nacional.php`.
- **P-06** alíquotas nominais estimadas de IBS/CBS por ano (2027–2033).
- **P-02** a comparação usa só IBS/CBS; não usamos o modo CRT=3 + `tpRegimeIBSCBS=2` da calculadora do Simples.
- **P-11** crédito do comprador = valor pago no DAS; **P-07** 70% de fornecedores no regime regular;
  **P-04** 50% do crédito extra repassado como vantagem; **P-10** limiar de 0,5% da receita para "indiferente".
- Prazos/periodicidade da opção pelo regime regular (LC 214) não foram validados.

### 3. Para vender (pedir ao dono para escolher antes de implementar)
- **Cobrança real**: hoje é `NullBillingGateway`. Implementar Asaas ou Stripe atrás de
  `app/Services/Billing/BillingGateway.php` (assinatura, webhook, status `plan_status`). Dono não escolheu ainda.
- **Recuperação de senha + envio de e-mail** (SMTP/serviço transacional a definir).
- **Termos de Uso e Política de Privacidade (LGPD)**: páginas, links no rodapé da landing e aceite no cadastro.
  O texto jurídico deve vir do dono/advogado — não redigir como se fosse definitivo.
- **CAPTCHA** no login/cadastro (Turnstile, hCaptcha ou reCAPTCHA — dono escolhe). O login já tem limite de
  tentativas por e-mail+rede e teto por conta (`AuthController::login`); não reabrir esse desenho sem motivo.
- **Preços**: R$ 99/299/599 e "14 dias sem cartão" são provisórios — conferir com o dono e manter
  `frontend/src/pages/Landing.tsx` igual a `backend/config/plans.php`.

### 4. Ajustes menores
- Landing não foi conferida visualmente em largura de celular (classes responsivas existem).
- Domínio, e-mail de contato e logo definitivos não existem ainda.
- Deploy real na VM nunca foi feito: seguir `docs/deploy.md` (`.env.production.example` → `.env`, `scripts/deploy.sh`).

## Ambiente local (Windows)
- Rodar: `cd backend && php artisan serve --port=8000` e `cd frontend && npx vite --port 5173`
  (usar `http://127.0.0.1:5173`; outro projeto da máquina às vezes ocupa `localhost:5173` via IPv6).
- Repositório da calculadora regime regular exige `git config core.longpaths true` no Windows.
- Memória da máquina é curta: evitar deixar muitos servidores/containers em segundo plano.
