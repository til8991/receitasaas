# Premissas

Regras de negócio que **não vêm das calculadoras oficiais**. Cada premissa vira um parâmetro configurável (padrão por escritório, com sobrescrita por cliente) e é listada no relatório da simulação.

> Simulação baseada na Calculadora oficial da Receita Federal. Não substitui análise profissional.

| ID | Premissa | Origem | Status |
|---|---|---|---|
| P-01 | **Alíquota efetiva do Simples para IBS/CBS (`pIBSSN`/`pCBSSN`).** A calculadora do Simples recebe essas alíquotas como **entrada** e não as calcula a partir do faturamento de 12 meses, do anexo e da faixa. Precisamos calculá-las (LC 123/2006 + LC 214/2025: alíquota efetiva da faixa × percentual de repartição de IBS/CBS no anexo) ou recebê-las do contador. | Legislação | **Em aberto — definir na Fase 3** |
| P-02 | **Cenário híbrido = `CRT=3` + `tpRegimeIBSCBS=2` na calculadora do Simples** (IBS/CBS vão para `…PendSusp`), com o IBS/CBS calculado pela calculadora do regime regular. Precisa ser confirmado contra a NT 2026.005. | NT 2026.005 / código | **A confirmar — Fase 3** |
| P-03 | Percentual de vendas B2B (que geram crédito ao adquirente). | Cadastro do cliente | Parâmetro |
| P-04 | Repasse de crédito: quanto do crédito gerado ao cliente B2B se converte em preço ou competitividade. | Premissa comercial | Parâmetro |
