import { useState } from 'react'
import { Link } from 'react-router-dom'
import { DISCLAIMER } from '../components/ui'

/*
  Public landing page. Written for two readers: the accountant (who buys) and anyone
  who has never heard of IBS/CBS (who needs the problem explained in plain words).
  The one bold element is the razonete in the hero; everything else stays quiet.
*/

const plans = [
  { name: 'Essencial', limit: 'Até 10 CNPJs ativos', price: 99, note: 'Para começar com a carteira principal.' },
  { name: 'Profissional', limit: 'Até 50 CNPJs ativos', price: 299, note: 'Para escritórios com carteira média.', featured: true },
  { name: 'Ilimitado', limit: 'CNPJs ilimitados', price: 599, note: 'Para escritórios grandes e redes.' },
]

const faqs: [string, string][] = [
  ['Isso substitui o contador?',
    'Não. A ferramenta faz as contas e organiza a comparação, mas a decisão é do contador junto com o cliente. Por isso cada relatório lista as premissas usadas e traz o aviso de que não substitui análise profissional.'],
  ['De onde vêm os valores dos impostos?',
    'IBS, CBS e Imposto Seletivo são calculados pelas calculadoras oficiais da Receita Federal, que são públicas e de código aberto. Elas rodam dentro do nosso servidor, e cada simulação registra qual versão foi usada.'],
  ['Quais dados eu preciso ter?',
    'O ideal são os arquivos XML das notas fiscais do cliente (NF-e, NFC-e e NFS-e) dos últimos 12 meses, que o escritório já costuma ter. Sem as notas, dá para simular com o faturamento anual, o percentual de vendas para empresas e o valor das compras.'],
  ['Preciso instalar alguma coisa?',
    'Não. Funciona no navegador, no computador ou no celular. É só criar a conta do escritório.'],
  ['E se as regras ou as alíquotas mudarem?',
    'As calculadoras oficiais são atualizadas pela Receita e nós atualizamos junto. O que a lei ainda não definiu, como as alíquotas finais de cada ano, fica como premissa ajustável nas configurações do escritório e aparece no relatório.'],
  ['Os dados dos meus clientes ficam seguros?',
    'Cada escritório só enxerga os próprios clientes. As notas são processadas no nosso servidor e não são enviadas para terceiros, nem para a Receita: as calculadoras oficiais rodam dentro do próprio sistema.'],
  ['Posso cancelar quando quiser?',
    'Sim. O teste é de 14 dias, sem cartão. Depois, a assinatura é mensal e pode ser cancelada a qualquer momento.'],
]

export default function Landing() {
  return (
    <div className="bg-paper text-ink">
      <SiteHeader />
      <main id="conteudo">
        <Hero />
        <PlainExplanation />
        <HowItWorks />
        <WhatYouGet />
        <WhoBenefits />
        <DataSource />
        <Pricing />
        <Faq />
        <FinalCall />
      </main>
      <SiteFooter />
    </div>
  )
}

function Logo({ light = false }: { light?: boolean }) {
  return (
    <span className="flex items-center gap-2.5">
      <svg viewBox="0 0 32 32" className="h-8 w-8" aria-hidden>
        <rect width="32" height="32" rx="6" fill="#0B5D4B" />
        <path d="M6 10h20M16 10v15" stroke="#F5F7F6" strokeWidth="3" strokeLinecap="round" />
      </svg>
      <span className={`text-lg font-bold ${light ? 'text-white' : 'text-ink'}`} style={{ fontStretch: '115%' }}>Razonete</span>
    </span>
  )
}

function SiteHeader() {
  const [open, setOpen] = useState(false)
  const links = [['#como-funciona', 'Como funciona'], ['#para-quem', 'Para quem é'], ['#precos', 'Preços'], ['#duvidas', 'Dúvidas']]
  return (
    <header className="sticky top-0 z-30 border-b border-rule bg-paper/90 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="#topo" aria-label="Razonete, início"><Logo /></a>
        <nav className="hidden items-center gap-7 text-[15px] text-ink-soft md:flex" aria-label="Seções">
          {links.map(([href, label]) => <a key={href} href={href} className="hover:text-ink">{label}</a>)}
        </nav>
        <div className="flex items-center gap-2">
          <Link to="/entrar" className="btn-ghost hidden sm:inline-flex">Entrar</Link>
          <Link to="/cadastro" className="btn-primary">Testar grátis</Link>
          <button className="btn-ghost md:hidden" aria-expanded={open} aria-controls="menu-site" onClick={() => setOpen((o) => !o)}>Menu</button>
        </div>
      </div>
      {open && (
        <nav id="menu-site" className="border-t border-rule px-4 py-2 md:hidden" aria-label="Seções">
          {links.map(([href, label]) => <a key={href} href={href} onClick={() => setOpen(false)} className="block py-2 text-ink-soft">{label}</a>)}
          <Link to="/entrar" className="block py-2 font-semibold">Entrar</Link>
        </nav>
      )}
    </header>
  )
}

function Hero() {
  return (
    <section id="topo" className="mx-auto grid max-w-6xl items-center gap-12 px-4 pb-20 pt-14 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:pt-20">
      <div>
        <p className="mb-4 inline-block rounded-full border border-rule bg-sheet px-3 py-1 text-sm text-ink-soft">
          Reforma Tributária, para escritórios de contabilidade
        </p>
        <h1 className="text-[34px] font-bold leading-[1.1] sm:text-5xl" style={{ fontStretch: '115%' }}>
          Seu cliente do Simples deve pagar IBS e CBS dentro ou fora do DAS?
        </h1>
        <p className="mt-5 max-w-[56ch] text-lg leading-relaxed text-ink-soft">
          A partir de 2027, cada empresa do Simples Nacional pode escolher como recolher os novos impostos sobre o consumo.
          A escolha muda quanto ela paga e quanto crédito ela dá a quem compra dela. O Razonete faz essa conta para cada
          cliente do seu escritório, com as calculadoras oficiais da Receita Federal, e entrega a resposta em um relatório com a sua marca.
        </p>
        <div className="mt-8 flex flex-wrap gap-3">
          <Link to="/cadastro" className="btn-primary px-5 py-3 text-base">Testar 14 dias grátis</Link>
          <a href="#como-funciona" className="btn-secondary px-5 py-3 text-base">Ver como funciona</a>
        </div>
        <p className="mt-3 text-sm text-muted">Sem cartão de crédito. Sem instalar nada.</p>
      </div>
      <HeroRazonete />
    </section>
  )
}

/** The product's answer, drawn the way accountants draw it: a T-account. */
function HeroRazonete() {
  const rows: [string, string, string][] = [
    ['Imposto pago (IBS/CBS)', 'R$ 57.395', 'R$ 92.560'],
    ['Crédito para quem compra', 'R$ 54.525', 'R$ 270.560'],
    ['Resultado no ano (imposto menos ganho comercial)', 'R$ 57.395', '− R$ 80.267'],
  ]
  return (
    <figure className="sheet animate-settle overflow-hidden shadow-[0_1px_0_var(--color-rule)]" aria-label="Exemplo de resultado">
      <div className="px-6 pb-5 pt-6">
        <div className="text-sm text-ink-soft">Distribuidora de bebidas, 95% das vendas para empresas</div>
        <div className="mt-1 text-xl font-bold text-migrate" style={{ fontStretch: '112%' }}>Compensa pagar fora do Simples</div>
        <div className="figure mt-2 text-4xl font-bold text-migrate sm:text-[44px]">R$ 137.662</div>
        <div className="text-sm text-ink-soft">de vantagem por ano</div>
      </div>
      <div className="grid grid-cols-[1fr_3px_1fr] border-t-[3px] border-ink text-[15px]">
        <div className="px-4 py-2 text-right font-semibold sm:px-6" style={{ fontStretch: '112%' }}>Dentro do Simples</div>
        <div className="bg-ink" />
        <div className="px-4 py-2 font-semibold sm:px-6" style={{ fontStretch: '112%' }}>Fora do Simples</div>
        {rows.map(([label, a, b], i) => (
          <Row key={label} label={label} a={a} b={b} delay={150 + i * 120} strong={i === rows.length - 1} />
        ))}
      </div>
      <figcaption className="border-t border-rule-soft px-6 py-3 text-xs text-muted">
        Exemplo ilustrativo. O resultado de cada empresa depende das vendas, das compras e das premissas usadas.
      </figcaption>
    </figure>
  )
}

function Row({ label, a, b, delay, strong }: { label: string; a: string; b: string; delay: number; strong?: boolean }) {
  const cell = 'border-t border-rule-soft px-4 py-2.5 sm:px-6 animate-settle'
  return (
    <>
      <div className={`${cell} text-right ${strong ? 'bg-paper font-semibold' : ''}`} style={{ animationDelay: `${delay}ms` }}>
        <div className="text-xs text-muted">{label}</div><div className="figure">{a}</div>
      </div>
      <div className="bg-ink" />
      <div className={`${cell} ${strong ? 'bg-paper font-semibold' : ''}`} style={{ animationDelay: `${delay}ms` }}>
        <div className="text-xs text-muted">{label}</div><div className="figure">{b}</div>
      </div>
    </>
  )
}

function Section({ id, title, intro, children, tone = 'paper' }: {
  id?: string; title: string; intro?: string; children: React.ReactNode; tone?: 'paper' | 'sheet' | 'ink'
}) {
  const bg = tone === 'sheet' ? 'bg-sheet border-y border-rule' : tone === 'ink' ? 'bg-ink text-white' : ''
  return (
    <section id={id} className={`${bg} scroll-mt-16`}>
      <div className="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <h2 className="max-w-[24ch] text-3xl font-bold leading-tight sm:text-4xl">{title}</h2>
        {intro && <p className={`mt-4 max-w-[64ch] text-lg leading-relaxed ${tone === 'ink' ? 'text-white/75' : 'text-ink-soft'}`}>{intro}</p>}
        <div className="mt-10">{children}</div>
      </div>
    </section>
  )
}

function PlainExplanation() {
  return (
    <Section tone="sheet" title="O que muda, em português claro"
      intro="Não precisa ser contador para entender a decisão. São três ideias.">
      <div className="grid gap-10 lg:grid-cols-3">
        <Idea title="Novos impostos sobre o consumo"
          text="A Reforma Tributária troca PIS, COFINS, ICMS e ISS por dois impostos novos: a CBS (federal) e o IBS (estados e municípios). A troca começa de verdade em 2027." />
        <Idea title="Quem compra quer crédito"
          text="Nesses impostos, a empresa que compra desconta do próprio imposto o que veio cobrado na nota do fornecedor. Esse desconto se chama crédito. Quanto mais crédito a sua nota gera, mais vantajoso fica comprar de você." />
        <Idea title="O Simples pode escolher"
          text="A empresa do Simples pode continuar pagando tudo na guia única (DAS), o que gera pouco crédito para o comprador, ou pagar IBS e CBS por fora, gerando crédito integral. Pagar por fora pode custar mais imposto, mas pode trazer clientes. Depende de cada caso." />
      </div>

      <div className="mt-14 overflow-hidden rounded-lg border border-rule">
        <div className="grid sm:grid-cols-2">
          <CreditFlow title="Pagando dentro do Simples" credit="crédito pequeno" width="18%" tone="bg-accent" text="Seu cliente empresa recebe pouco crédito na nota. Você paga menos imposto." />
          <CreditFlow title="Pagando IBS/CBS por fora" credit="crédito integral" width="92%" tone="bg-brand" text="Seu cliente empresa recebe o crédito cheio. Você paga mais imposto, mas sua nota vale mais para ele." border />
        </div>
      </div>
      <p className="mt-6 max-w-[70ch] text-ink-soft">
        <strong className="text-ink">A pergunta é: qual dos dois sai mais barato no fim, para cada empresa?</strong> É isso que o Razonete responde,
        com números e a justificativa por escrito.
      </p>
    </Section>
  )
}

function Idea({ title, text }: { title: string; text: string }) {
  return (
    <div className="border-l-[3px] border-brand pl-5">
      <h3 className="text-lg font-bold">{title}</h3>
      <p className="mt-2 leading-relaxed text-ink-soft">{text}</p>
    </div>
  )
}

function CreditFlow({ title, credit, width, tone, text, border }: { title: string; credit: string; width: string; tone: string; text: string; border?: boolean }) {
  return (
    <div className={`bg-paper p-6 ${border ? 'border-t border-rule sm:border-l sm:border-t-0' : ''}`}>
      <div className="font-semibold">{title}</div>
      <div className="mt-4 flex items-center gap-3 text-sm text-ink-soft">
        <span className="shrink-0">Você vende</span>
        <span aria-hidden className="h-px flex-1 bg-rule" />
        <span className="shrink-0">Cliente empresa</span>
      </div>
      <div className="mt-3 h-3 overflow-hidden rounded-full bg-rule-soft" role="img" aria-label={credit}>
        <div className={`h-full ${tone}`} style={{ width }} />
      </div>
      <div className="mt-1 text-sm font-semibold">{credit}</div>
      <p className="mt-3 text-sm leading-relaxed text-ink-soft">{text}</p>
    </div>
  )
}

function HowItWorks() {
  const steps: [string, string][] = [
    ['Cadastre o cliente e envie as notas',
      'Arraste os arquivos XML das notas fiscais (NF-e, NFC-e e NFS-e) ou um ZIP com todas. Sem notas? Informe o faturamento, quanto vende para empresas e quanto compra.'],
    ['O sistema calcula os dois cenários',
      'Cada venda e cada compra passam pelas calculadoras oficiais da Receita Federal, uma para o Simples e outra para o regime regular. Em seguida o sistema compara imposto pago, crédito gerado e custo final.'],
    ['Entregue a resposta ao cliente',
      'Você vê a recomendação, a diferença em reais e o porquê. Baixe o relatório em PDF com o logo e as cores do seu escritório e apresente ao cliente.'],
  ]
  return (
    <Section id="como-funciona" title="Como funciona" intro="Três passos. Para um cliente ou para a carteira inteira de uma vez.">
      <ol className="grid gap-8 lg:grid-cols-3">
        {steps.map(([title, text], i) => (
          <li key={title} className="relative">
            <div className="figure mb-3 text-5xl font-bold text-brand/25">{i + 1}</div>
            <h3 className="text-xl font-bold">{title}</h3>
            <p className="mt-2 leading-relaxed text-ink-soft">{text}</p>
          </li>
        ))}
      </ol>
    </Section>
  )
}

function WhatYouGet() {
  const items: [string, string][] = [
    ['Comparação lado a lado', 'Quanto a empresa paga e quanto crédito gera em cada cenário, com a recomendação: compensa sair, manter no Simples ou tanto faz.'],
    ['Justificativa por escrito', 'Cada resultado vem com a explicação das contas, em frases que o seu cliente entende.'],
    ['Simulação em lote', 'Rode a carteira inteira de uma vez e veja quais clientes têm mais a ganhar, ordenados pela economia.'],
    ['Relatório com a sua marca', 'PDF pronto para enviar, com logo, cores e contatos do escritório.'],
    ['Leitura automática das notas', 'Entende NF-e, NFC-e, NFS-e Nacional e municipal. Separa vendas e compras, e venda para empresa ou para consumidor.'],
    ['Premissas ajustáveis', 'O que a lei ainda não definiu fica configurável e aparece no relatório. Nada escondido.'],
    ['Trilha de auditoria', 'Cada simulação guarda o que foi enviado às calculadoras oficiais, a resposta delas e a versão usada.'],
    ['Painel do escritório', 'Quantos clientes compensa migrar e a economia potencial somada da carteira.'],
  ]
  return (
    <Section tone="sheet" title="O que o seu escritório recebe">
      <dl className="grid gap-x-12 gap-y-8 sm:grid-cols-2">
        {items.map(([title, text]) => (
          <div key={title} className="border-t border-rule pt-4">
            <dt className="font-bold">{title}</dt>
            <dd className="mt-1 leading-relaxed text-ink-soft">{text}</dd>
          </div>
        ))}
      </dl>
    </Section>
  )
}

function WhoBenefits() {
  return (
    <Section id="para-quem" title="Para quem é"
      intro="Feito para escritórios de contabilidade que atendem empresas do Simples Nacional e querem responder essa pergunta antes que o cliente pergunte.">
      <div className="grid gap-6 lg:grid-cols-2">
        <div className="rounded-lg border border-migrate/30 bg-migrate/5 p-6">
          <h3 className="text-lg font-bold text-migrate">Costuma compensar pagar por fora</h3>
          <p className="mt-2 text-ink-soft">Empresas que vendem principalmente para outras empresas, onde o crédito pesa na escolha do fornecedor:</p>
          <ul className="mt-3 list-disc space-y-1 pl-5 text-ink-soft">
            <li>distribuidoras e atacadistas</li>
            <li>pequenas indústrias que vendem para revenda</li>
            <li>prestadores de serviço para empresas (TI, manutenção, consultoria)</li>
          </ul>
        </div>
        <div className="rounded-lg border border-keep/30 bg-keep/5 p-6">
          <h3 className="text-lg font-bold text-keep">Costuma compensar ficar no Simples</h3>
          <p className="mt-2 text-ink-soft">Empresas que vendem para o consumidor final, que não usa crédito:</p>
          <ul className="mt-3 list-disc space-y-1 pl-5 text-ink-soft">
            <li>padarias, restaurantes e varejo de bairro</li>
            <li>salões, academias e serviços pessoais</li>
            <li>comércio eletrônico para pessoa física</li>
          </ul>
        </div>
      </div>
      <p className="mt-6 text-ink-soft">
        Mas a regra geral engana. Margem, compras e mistura de clientes mudam a resposta, e por isso a conta é feita empresa por empresa.
      </p>
    </Section>
  )
}

function DataSource() {
  return (
    <Section tone="ink" title="De onde vêm os números"
      intro="Transparência é o que permite ao contador assinar embaixo da recomendação.">
      <div className="grid gap-10 lg:grid-cols-3">
        <Source title="Calculadoras oficiais da Receita Federal"
          text="IBS, CBS e Imposto Seletivo são calculados pelas calculadoras públicas da Receita, para o regime regular e para o Simples Nacional. Elas rodam dentro do nosso servidor." />
        <Source title="As notas fiscais do seu cliente"
          text="Valores, NCM/NBS, classificação tributária e quem comprou (empresa ou consumidor) vêm dos XMLs que você envia, considerando os últimos 12 meses." />
        <Source title="Premissas à vista"
          text="Pontos que a lei ainda não fechou, como as alíquotas finais de cada ano, ficam configuráveis e listados em cada relatório." />
      </div>
    </Section>
  )
}

function Source({ title, text }: { title: string; text: string }) {
  return (
    <div className="border-t border-white/20 pt-5">
      <h3 className="text-lg font-bold">{title}</h3>
      <p className="mt-2 leading-relaxed text-white/75">{text}</p>
    </div>
  )
}

function Pricing() {
  return (
    <Section id="precos" title="Preços" intro="Cobrança mensal pelo número de empresas ativas na sua carteira. Teste grátis por 14 dias com todas as funções.">
      <div className="grid gap-5 lg:grid-cols-3">
        {plans.map((p) => (
          <div key={p.name} className={`flex flex-col rounded-lg border bg-sheet p-6 ${p.featured ? 'border-brand ring-1 ring-brand' : 'border-rule'}`}>
            <div className="flex items-baseline justify-between gap-3">
              <h3 className="text-xl font-bold">{p.name}</h3>
              {p.featured && <span className="text-sm font-semibold text-brand">Recomendado</span>}
            </div>
            <div className="mt-1 text-ink-soft">{p.limit}</div>
            <div className="mt-6 flex items-baseline gap-1">
              <span className="figure text-4xl font-bold">R$ {p.price}</span>
              <span className="text-muted">/mês</span>
            </div>
            <p className="mt-2 text-sm text-ink-soft">{p.note}</p>
            <ul className="mt-5 space-y-1.5 text-sm text-ink-soft">
              <li>Simulações e lotes ilimitados</li>
              <li>Importação de XML</li>
              <li>Relatórios com a sua marca</li>
              <li>Usuários ilimitados no escritório</li>
            </ul>
            <Link to="/cadastro" className={`${p.featured ? 'btn-primary' : 'btn-secondary'} mt-auto w-full py-2.5`} style={{ marginTop: '1.5rem' }}>
              Começar teste grátis
            </Link>
          </div>
        ))}
      </div>
      <p className="mt-4 text-sm text-muted">Empresa ativa é o CNPJ marcado como ativo no cadastro. Clientes inativos não contam.</p>
    </Section>
  )
}

function Faq() {
  return (
    <Section id="duvidas" tone="sheet" title="Dúvidas frequentes">
      <div className="max-w-3xl divide-y divide-rule border-y border-rule">
        {faqs.map(([q, a]) => (
          <details key={q} className="group py-4">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-lg font-semibold">
              {q}
              <span aria-hidden className="text-2xl leading-none text-brand transition-transform group-open:rotate-45">+</span>
            </summary>
            <p className="mt-3 max-w-[68ch] leading-relaxed text-ink-soft">{a}</p>
          </details>
        ))}
      </div>
    </Section>
  )
}

function FinalCall() {
  return (
    <section className="mx-auto max-w-6xl px-4 py-20 text-left sm:px-6">
      <div className="grid items-center gap-8 border-t-[3px] border-ink pt-10 lg:grid-cols-[1.4fr_1fr]">
        <div>
          <h2 className="text-3xl font-bold leading-tight sm:text-4xl">Chegue em 2027 com a resposta pronta para cada cliente.</h2>
          <p className="mt-4 max-w-[56ch] text-lg text-ink-soft">
            Crie a conta do escritório, cadastre um cliente e veja o primeiro resultado em poucos minutos.
          </p>
        </div>
        <div className="flex flex-wrap gap-3 lg:justify-end">
          <Link to="/cadastro" className="btn-primary px-5 py-3 text-base">Testar 14 dias grátis</Link>
          <Link to="/entrar" className="btn-secondary px-5 py-3 text-base">Já tenho conta</Link>
        </div>
      </div>
    </section>
  )
}

function SiteFooter() {
  return (
    <footer className="bg-ink text-white/70">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-[1.5fr_1fr_1fr]">
        <div>
          <Logo light />
          <p className="mt-4 max-w-[46ch] text-sm leading-relaxed">
            Comparação do IBS e da CBS no Simples Nacional e no regime regular, com as calculadoras oficiais da Receita Federal.
          </p>
        </div>
        <nav aria-label="Produto" className="text-sm">
          <div className="mb-2 font-semibold text-white">Produto</div>
          <a href="#como-funciona" className="block py-1 hover:text-white">Como funciona</a>
          <a href="#precos" className="block py-1 hover:text-white">Preços</a>
          <a href="#duvidas" className="block py-1 hover:text-white">Dúvidas</a>
        </nav>
        <nav aria-label="Conta" className="text-sm">
          <div className="mb-2 font-semibold text-white">Conta</div>
          <Link to="/cadastro" className="block py-1 hover:text-white">Criar conta</Link>
          <Link to="/entrar" className="block py-1 hover:text-white">Entrar</Link>
        </nav>
      </div>
      <div className="border-t border-white/10">
        <p className="mx-auto max-w-6xl px-4 py-5 text-xs sm:px-6">{DISCLAIMER}</p>
      </div>
    </footer>
  )
}
