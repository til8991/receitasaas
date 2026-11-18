# Premissas do comparador

> **Simulação baseada na Calculadora oficial da Receita Federal. Não substitui análise profissional.**

Os **tributos** (IBS, CBS, IS) são calculados pelas calculadoras oficiais da Receita Federal (`services/`). Este documento lista tudo o que **não** vem delas: são as regras de negócio do comparador.

- As premissas configuráveis estão em `backend/config/premissas.php` e `backend/config/simples_nacional.php`.
- Cada escritório pode sobrescrever os padrões em *Configurações → Premissas* (`offices.assumptions`), e cada cliente pode sobrescrever de novo (`clients.assumptions`).
- O conjunto efetivamente usado fica gravado em cada comparação (`comparisons.assumptions`) e é impresso no PDF.

Legenda: ⚠️ = premissa de legislação que o contador deve validar.

| ID | Premissa | Valor padrão | Onde muda |
|---|---|---|---|
| P-01 ⚠️ | **Alíquotas efetivas de IBS/CBS no Simples (`pIBSSN`/`pCBSSN`).** A calculadora do Simples recebe essas alíquotas prontas (`ItemSimplesInput`). Calculamos pela LC 123, art. 18: `efetiva = (RBT12 × nominal − parcela a deduzir) / RBT12`. Depois, `pCBSSN = efetiva × (PIS + COFINS)%` e `pIBSSN = efetiva × (ICMS ou ISS)% × fator de transição`. A parcela do ISS é limitada a 5 p.p. Sem RBT12, usa a alíquota nominal da 1ª faixa. As tabelas dos Anexos I a V (redação da LC 155/2016) foram **transcritas e devem ser conferidas**. Se a repartição oficial pós-LC 214 for diferente, ajuste a tabela ou informe `p_ibs_sn`/`p_cbs_sn` manualmente. | Tabelas da LC 123 | `config/simples_nacional.php`; premissas `p_ibs_sn`/`p_cbs_sn` |
| P-02 ⚠️ | **A comparação se restringe a IBS/CBS.** Os demais tributos do DAS (IRPJ, CSLL, CPP, IPI) são considerados iguais nos dois cenários. No cenário regular, a parcela de IBS/CBS sai do DAS e é apurada fora dele. Não usamos `CRT=3` + `tpRegimeIBSCBS=2` da calculadora do Simples porque a semântica exata desse par na NT 2026.005 ainda precisa ser confirmada. | — | Código (`ComparadorService`) |
| P-03 | **Vendas B2B.** Com notas importadas, conta como B2B o destinatário **PJ** (CNPJ). Na simulação manual, vale o % do cadastro ou o % informado. | Cadastro do cliente | Cliente / tela de simulação |
| P-04 | **Repasse do crédito ao preço.** Fração do crédito **adicional** de IBS/CBS (regular − Simples) que os clientes B2B passam a receber e que vira benefício comercial para a empresa. | 50% | Configurações / cliente |
| P-05 ⚠️ | **Transição no DAS.** A CBS substitui PIS/COFINS a partir de 2027. O IBS substitui uma fração do ICMS/ISS: 0 até 2028; 10% em 2029, 20% em 2030, 30% em 2031, 40% em 2032 e 100% em 2033. | Tabela da LC 214 | `simples_ibs_transition`, `simples_cbs_start_year` |
| P-06 ⚠️ | **Alíquotas nominais do regime regular** (`aliquotasNominais`, obrigatório a partir de 2027). As alíquotas de referência ainda serão fixadas, então os valores são **estimativas**: CBS 8,8% (como no exemplo oficial), IBS 0,1% em 2027–2028 (0,05 + 0,05) e IBS de referência de 17,7% em 2033 (2/3 UF, 1/3 município), com transição proporcional. | Ver `config/premissas.php` | Configurações / cliente |
| P-07 | **Fornecedores do regime regular.** Fração das compras que gera crédito integral no cenário regular. Compras de fornecedores do Simples entram com crédito **zero** (conservador). | 70% | Configurações / cliente |
| P-08 | **Período base.** Com notas, usam-se os 12 meses que terminam na última nota de saída importada, sem projeção de inflação ou crescimento. As notas de devolução de compra (CFOP x2xx) emitidas pelo cliente são excluídas da receita. | — | Código |
| P-09 | **Classificação padrão.** Itens sem grupo `IBSCBS` no XML (notas anteriores à reforma) usam o CST/cClassTrib padrão do cliente (`000`/`000001`, tributação integral). Itens sem NCM/NBS usam a NCM/NBS padrão. | `000` / `000001` | Cadastro do cliente |
| P-10 | **Limite de indiferença.** Se a diferença de custo efetivo for menor que esse % da receita, a recomendação é "indiferente". | 0,5% | Configurações / cliente |
| P-11 ⚠️ | **Crédito do adquirente de empresa do Simples** = IBS/CBS efetivamente recolhido no DAS (LC 214). | — | Código |

## Fórmula da recomendação

```
custo_simples   = IBS + CBS recolhidos no DAS                                  (calculadora do Simples)
custo_regular   = débito IBS/CBS das vendas − crédito das compras × P-07       (calculadora do regime regular)
crédito_extra   = crédito aos clientes B2B no regular − crédito aos clientes B2B no Simples
benefício       = max(crédito_extra, 0) × P-04
custo_efetivo   = custo_regular − benefício
diferença       = custo_efetivo − custo_simples          (negativa ⇒ o regime regular compensa)
recomendação    = |diferença / receita| < P-10 ? indiferente : (diferença < 0 ? migrar_regular : manter_simples)
```

## Limitações conhecidas
- Uma única atividade do Simples (`cAtivSN`) por cliente: a primeira cadastrada vale para todas as receitas.
- O Fator R (Anexo III × V) não é calculado: o contador informa o anexo do cliente.
- Créditos presumidos e regimes específicos (combustíveis, financeiro, imobiliário etc.) não são tratados pelo comparador, embora a calculadora oficial os suporte por cClassTrib.
- Itens com erro na calculadora (observabilidade) ficam fora dos totais e aparecem na aba "Erros".
