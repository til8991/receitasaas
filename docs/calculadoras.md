# Calculadoras oficiais da Receita Federal

Os dois repositórios ficam como git submodules em `services/` e **não são modificados**.
Para atualizar: `scripts/update-calculadoras.sh`.

| | Regime regular | Simples Nacional |
|---|---|---|
| Repositório | `services/calculadora-rtc-regime-regular` | `services/calculadora-rtc-simples-nacional` |
| Versão do app (pom) | 1.6.1 | 0.0.6 |
| Serviço / URL interna | `calc-regular` → `http://calc-regular:8080/api` | `calc-simples` → `http://calc-simples:8082/api` |
| JAR | `target/api-regime-geral.jar` | `target/api-simples-nacional.jar` |
| Versão | `GET /calculadora/dados-abertos/versao` | `GET /versao` |
| Cálculo (observabilidade) | `POST /calculadora/observabilidade/regime-geral` | `POST /calculadora/observabilidade/simples-nacional` |
| Descrição de erro | `GET /calculadora/observabilidade/erros?codigo=REG-011` | `GET /calculadora/observabilidade/simples-nacional/erros?codigo=VSN-001` |
| Swagger | `/api/swagger-ui.html` | `/api/swagger-ui.html` |
| Autenticação | nenhuma (`permitAll`) | nenhuma |

Como as APIs não têm autenticação, elas **não publicam portas**. Ficam na rede Docker `calc` (`internal: true`).
Para abrir o Swagger em desenvolvimento, use um override local com `ports`.

## Divergências encontradas na documentação oficial (verificadas no código)

1. **O Simples não tem `flyway/`.** O README manda copiar a pasta `flyway/` "do repositório calculadora-rtc-simples-nacional", que é o próprio repositório. Todas as tabelas lidas pelo Simples (`ATIVIDADE_SIMPLES_NACIONAL`, `CLASSIFICACAO_TRIBUTARIA`, `REGIME_APURACAO_SIMPLES_NACIONAL`, `TIPO_DFE_CALCULO_SIMPLES_NACIONAL`, `TIPO_RECEITA_BRUTA_SIMPLES_NACIONAL`, `TRANSICAO_ALIQUOTA_COMPRA_GOV_SIMPLES_NACIONAL`, `VERSAO_BASE_DADO`) são criadas pelas migrations do **regime-regular** (V0047, V0049, V0050 e outras).
   **Decisão:** o Dockerfile do Simples copia `flyway/` do submodule regime-regular e roda o `mvnw flyway:migrate` do próprio Simples. As duas imagens sempre são reconstruídas juntas, e o teste de fumaça falha se o `versaoDb` delas divergir.
2. **O `docs/exemplos.md` do Simples é cópia do regime-regular.** Não existe exemplo de Simples. O payload de `smoke/simples-nacional.json` foi montado com os valores `@Schema(example=…)` de `OperacaoSimplesInput` e `ItemSimplesInput`.
3. **O README do Simples (passos 5 e 6) cita a porta 8080 e `api-regime-geral.jar`.** No código, a porta é 8082 (`application-offline.yml`) e o JAR é `api-simples-nacional.jar`.
4. **O endpoint de versão tem caminho diferente em cada calculadora** (ver tabela).
5. O Simples tem um `DatabaseDownloadService` (`DB_DOWNLOAD_URL`). Ele não baixa nada quando o arquivo `calculadora/db/calculadora-pro.db` já existe, e é o caso aqui, porque o banco vai embutido na imagem.

## Contrato — Regime regular (`POST /calculadora/observabilidade/regime-geral`)

Fonte: `api/model/input/OperacaoInput.java`, `ItemOperacaoInput.java` e `docs/exemplos.md`.

**Entrada:** `id`*, `versao`*, `dhFatoGerador` (ISO-8601 com offset), `municipio`* (IBGE), `uf`, `tpDoc?`, `gCompraGov?`, `itens[]`.
**Item:** `numero`*, `ncm` | `nbs`, `cst`*, `cClassTrib`*, `baseCalculo`, `quantidade`, `unidade`, `impostoSeletivo?`, `tributacaoRegular?`, `gComponentesBC?`, `aliquotasNominais{cbs, ibsEstadual, ibsMunicipal, impostoSeletivo?}`.

- `aliquotasNominais` é **obrigatório a partir de 01/01/2027 e proibido antes**. Cálculos com alíquotas informadas saem como `CALCULO_SIMULADO`, com o header `x-warning-dados-simulados`.
- As alíquotas de referência (`/dados-abertos/aliquota-*`) só existem para 2026. A partir de 2027 a consulta retorna 404.
- `dataHoraEmissao` está obsoleto; use `dhFatoGerador`.

**Saída:** HTTP 200 mesmo com itens em erro.
`objetos[]{nObj, estadoItem, tribCalc{IBSCBS{CST, cClassTrib, gIBSCBS{vBC, gIBSUF, gIBSMun, vIBS, gCBS}}, IS?}, erros[]{code, title, pointer, detail}}`
e `total.tribCalc{ISTot{vIS}, IBSCBSTot{vBCIBSCBS, gIBS{vIBS}, gCBS{vCBS}}}`.
`estadoItem` ∈ `CALCULADO | CALCULO_SIMULADO | INCONSISTENCIA_ENTRADA | NAO_IMPLEMENTADO | FALHA_TECNICA`. Os totais somam só os itens calculados.

## Contrato — Simples Nacional (`POST /calculadora/observabilidade/simples-nacional`)

Fonte: `api/simplesnacional/model/input/*.java` e `model/output/*.java`.

**Entrada:** `id`, `versao`, `data` (AAAA-MM-DD; todos os lookups de vigência usam essa data), `tpDoc` (`55` NF-e, `65` NFC-e), `CRT` (`1` SN, `2` SN com excesso de sublimite, `3` regime normal, `4` MEI, este fora de escopo), `tpRegimeIBSCBS?` (`2` = apuração por fora, só com CRT=3), `tpEnteGov?` (1 a 4, compra governamental), `itens[]`.
**Item:** `numero`, `cAtivSN` (2 caracteres; sem ele, ou se inválido, o item sai `NAO_CALCULADO`), `cClassTrib`* (resolve o `tpRBSN`), `baseCalculo` (string, **sem** IS), `valorImpostoSeletivo` (string), `pIBSSN`, `pCBSSN` (**alíquotas efetivas do SN, em p.p., informadas pelo chamador**).

**Saída:** `oper{…}`, `objetos[]{nObj, estadoItem, tribCalc{vRBSNitem, tpRBSN, tpRBSNDescricao, vBC, pIBSSN, vIBSSN, vIBSPendSusp, pCBSSN, vCBSSN, vCBSPendSusp, IS{valor}, totalItem}, erros[]}`,
`total{vRBSNtot, vBC, vIBSSNtot, vIBSSNtotPendSusp, vCBSSNtot, vCBSSNtotPendSusp, totalIs, totalTributos}`.
`estadoItem` ∈ `CALCULADO | NAO_CALCULADO | NAO_IMPLEMENTADO | INCONSISTENCIA_ENTRADA | FALHA_TECNICA`.
`vBC = vRBSNitem × fator(tpRBSN)`: 1 e 4 → 1,0; 5 → 0,5; 0, 2, 3 e 9 → 0. Para `CRT=2`, os campos de IBS não aparecem.

## Docker

- `docker/calc-*/Dockerfile`: multi-stage `build` (`mvnw package`) → `db` (`mvnw flyway:migrate -Dflyway.configFiles=./flyway/flyway-pro.conf`) → `runtime` (JRE 21, perfil `offline`, usuário não-root, healthcheck no endpoint de versão).
- Teste de fumaça: `docker compose up -d --wait calc-regular calc-simples && docker compose --profile smoke run --rm --build smoke`.
