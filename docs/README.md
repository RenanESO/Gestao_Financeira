# 💰 MoneyControl — Sistema de Controle Financeiro Pessoal

> Sistema web de gestão financeira pessoal desenvolvido em **Laravel 12 + Livewire 3 + MaryUI + Tailwind CSS**.  
> Projetado para uso web e futuro aplicativo mobile, com banco de dados unificado.

## Estado da Implementação (26/10/2026)

**Fase 1 em andamento:** Laravel 12 inicializado, dependências PHP instaladas e estrutura
financeira criada em 11 migrations (perfil + dez tabelas), mais migrations de idempotência e estorno.
As 19 migrations, incluindo
infraestrutura, exclusão lógica de categorias/contas/formas e personalização individual,
estão aplicadas no MySQL local `gestao_financeira`. Os onze Models financeiros, relacionamentos,
casts, soft deletes, escopo de proprietário e Policies estão implementados.
`TitleService` implementa criação, parcelas, baixas parciais/totais, cancelamento e edição controlada;
`RecurrenceService` calcula o próximo ciclo, criado atomicamente pelo `TitleService`.
Valores são calculados em centavos com `Money`, e baixas exigem chave UUID de idempotência.
`FinancialAccountService` calcula saldo por conta/data; `CatalogService` gerencia cadastros
e preserva os vínculos históricos. Comandos de vencidos e inicialização dos cadastros padrão
estão implementados, com seeders que não criam usuários de demonstração.
`SettlementReversalService` estorna uma baixa integralmente sem apagar o histórico,
reabre principal, invalida recibos existentes e trata próximos ciclos sem movimentos.
`ReceiptService` emite PDF privado com snapshot, hash e numeração global; download
autorizado bloqueia estornos e arquivos inválidos. `NotificationService` gera alertas
internos deduplicados por parcela/vencimento e permite marcar leitura ou atualização manual.
Validação: **410 testes, 1948 assertions em SQLite** (26/10/2026); focal histórico MySQL de analytics:
**1 teste, 18 assertions**, após aplicar a migration aditiva de 23/10 no banco de testes.
A última regressão completa MySQL continua sendo **158 testes, 1003 assertions**, anterior
a essa migration; a suíte completa ainda precisa ser reexecutada.

A primeira interface está disponível: apresentação, login, registro, recuperação de senha,
painel, dashboard financeira analítica, cadastros, receitas/despesas, detalhes de títulos, recibos e notificações.
Registro cria atomicamente categorias/formas padrão, sem dados financeiros fictícios.
Cadastros permitem criação/edição/desativação e exclusão lógica com confirmação e restauração.
Listagens de títulos e cadastros têm **atalhos de arraste** (direita: baixa/editar; esquerda:
cancelar ou estornar/excluir), sempre com revalidação no servidor e modal de confirmação para
estorno, cancelamento e exclusão, além de botão flutuante de ajuda com tutorial dos gestos.
Todas as mensagens usam um componente único com ícone por tipo de operação. Títulos permitem criação simples,
parcelada ou recorrente (mensal/anual), consulta e edição controlada. A tela de detalhe
permite baixar parcial/totalmente com até 20 formas e ajustes, confere o total antes do envio, estornar com motivo/data,
cancelar sem baixas ativas e emitir/verificar recibo PDF separadamente.
UUIDs de baixa/estorno são mantidos para reenvio do mesmo pedido sem duplicação.
Contatos completos, preferências persistentes, indicadores mensais iniciais do painel e
dashboard financeira com filtros, gráficos e relatório imprimível também estão disponíveis.

**Personalização individual:** em **Preferências > Personalização do sistema**, cada
usuário pode definir nome, ícone, exibição do nome e dez cores, com restauração das cores
originais do tema claro/escuro.
Os seletores já mostram as cores do tema ativo na primeira abertura de uma conta nova;
o hexadecimal original aparece como sugestão quando não há personalização.
Salvar sem editar cores mantém o tema original.
Adaptada do projeto local `lpro` sem modificar a origem nem instalar dependências:
continua Laravel 12, Livewire 3, MaryUI, Tailwind 4 e DaisyUI 5.
O ícone aceita PNG/JPG/WebP até 2 MB e 2048 × 2048 pixels, fica privado e serve também
como favicon autenticado. Outros usuários e páginas públicas não são afetados.
O relatório da dashboard usa o nome personalizado; recibos PDF existentes permanecem
inalterados. A personalização não altera as cores semânticas financeiras dos gráficos.
Notificações externas e importação/exportação
continuam pendentes. Os módulos abaixo incluem o alvo completo, não apenas telas já entregues.
Livewire 3.8.10 e MaryUI 2.9 estão em produção. Tailwind 4/daisyUI 5 substituem o alvo
antigo Tailwind 3; Vite 6/plugin Laravel Vite 1 são compatíveis com Node 20.13.1.
O ambiente atual permanece em PHP 8.2.0; PHP 8.3+ segue pendente.
O banco definitivo agora é **MySQL 8.0+ (InnoDB)**, usando a instalação local do Wamp
(8.0.31). `pdo_mysql` está habilitado; Redis/`phpredis` continuam pendentes.
Migrations, índices, FKs, decimais, enums e consultas de proprietário foram verificados
no MySQL. Services usam transações, leituras com `lockForUpdate()` e índice único de idempotência.
Dez testes entre processos independentes confirmam espera de lock no InnoDB e exercitam
numeração, baixa, edição, cancelamento, estorno, PDF e alertas. Carga prolongada continua pendente.

O `.env` local e `.env.example` usam MySQL, porta 3306, banco `gestao_financeira`,
charset `utf8mb4` e collation `utf8mb4_unicode_ci`. Ajuste usuário e senha locais.
Cache/sessão/filas locais continuam em `database`; Redis permanece a stack alvo.
O ponto exato de retomada está em [STATUS_DESENVOLVIMENTO.md](STATUS_DESENVOLVIMENTO.md).
Contratos e limitações estão no [guia do desenvolvedor](DEVELOPER_GUIDE.md)
e no [histórico](AI_DEV_LOG.md).

---

## 📋 Índice

- [Visão Geral](#visão-geral)
- [Stack Tecnológica](#stack-tecnológica)
- [Banco de Dados](#banco-de-dados)
- [Módulos do Sistema](#módulos-do-sistema)
- [Decisões de Arquitetura](#decisões-de-arquitetura)
- [Estrutura de Diretórios](#estrutura-de-diretórios)
- [Instalação e Configuração](#instalação-e-configuração)
- [Repositório Git](#repositório-git)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Convenções do Projeto](#convenções-do-projeto)

---

## 🎯 Visão Geral

O **MoneyControl** é um assistente pessoal de despesas que permite ao usuário:

- Controlar títulos a **receber** e a **pagar** com parcelamentos
- Gerenciar **contas financeiras** (corrente, poupança, cartão, dinheiro)
- Cadastrar **clientes e fornecedores** vinculados aos títulos
- Acompanhar **categorias** de receitas e despesas
- Visualizar **dashboard** com indicadores mensais, saldos e próximos compromissos
- Receber **notificações** de títulos próximos ao vencimento
- Realizar a **baixa de títulos** com múltiplas formas de pagamento
- Gerar **recibos em PDF** para títulos baixados
- Importar e exportar **planilhas** (Excel/CSV)

O layout é inspirado em sistemas de gestão modernos (painel lateral, tema dark/light), simplificado para uso pessoal.

---

## ⚙️ Stack Tecnológica

| Camada | Tecnologia | Versão | Finalidade |
|---|---|---|---|
| Backend | **Laravel** | 12.x | Framework principal PHP |
| Frontend Reativo | **Livewire** | 3.x | Componentes reativos sem API REST |
| UI Components | **MaryUI / daisyUI** | 2.9 / 5.x | Componentes Blade e temas |
| CSS Framework | **Tailwind CSS** | 4.x | Estilização utilitária via plugin Vite |
| Build | **Vite / Laravel Vite Plugin** | 6.x / 1.x | Compatíveis com Node 20.13.1 |
| Autenticação | **Laravel Auth / Password Broker** | Nativo | Login, registro, reset de senha; Breeze permanece apenas em desenvolvimento |
| Banco de Dados | **MySQL** | 8.0+ | Banco relacional principal (InnoDB) |
| Cache / Session | **Redis** | 7+ | Cache de sessão e filas |
| PDF | **Laravel DomPDF** | Latest | Geração de recibos em PDF |
| Excel/CSV | **Laravel Excel (Maatwebsite)** | Latest | Importação/Exportação de planilhas |
| Notificações | **Laravel Notifications** | Nativo | Sistema de alertas in-app |

### Por que MySQL?

- Já instalado na máquina do desenvolvedor, via Wamp
- Suporte a JSON, CTEs e window functions no MySQL 8.0+
- Compatibilidade com Laravel Eloquent e migrations
- Transações ACID e chaves estrangeiras com InnoDB
- Ampla disponibilidade em hospedagens compartilhadas e cloud
- Futuro mobile acessará uma API Laravel; não dependerá de conexão direta ao banco

---

## 🗄️ Banco de Dados

O diagrama completo de entidades e relacionamentos está documentado em [`DATABASE.md`](DATABASE.md).

**Tabelas principais:**

| Tabela | Descrição |
|---|---|
| `users` | Usuários do sistema (Auth nativo, onboarding atômico) |
| `categories` | Categorias de receitas e despesas |
| `financial_accounts` | Contas financeiras do usuário |
| `contacts` | Clientes e fornecedores |
| `payment_types` | Tipos/formas de pagamento e recebimento |
| `titles` | Títulos a receber e a pagar |
| `title_installments` | Parcelas individuais de cada título |
| `title_settlements` | Baixas realizadas nos títulos |
| `title_settlement_methods` | Formas de pagamento usadas em cada baixa |
| `receipts` | Recibos gerados em PDF |
| `notifications` | Notificações do sistema |

---

## 🧩 Módulos do Sistema

### 1. 🔐 Autenticação
- Login / Logout via **Laravel Auth**, com regeneração/invalidação de sessão
- Registro de conta pessoal
- Reset de senha por Password Broker; envio externo depende de SMTP (local usa `log`)
- Gerenciamento de sessão

### 2. 📊 Dashboards
- Visão geral: resumo financeiro do mês atual e saldo por conta
- Dashboard financeira: filtros globais por período, categorias, formas, contas e contatos
- Cards de entradas, saídas, saldo líquido e quantidade de baixas/estornos
- Evolução diária de entradas versus saídas e distribuição por categoria e forma
- Relatório tabular imprimível com os agrupamentos Conta > Contato > Data, Conta > Data e Conta > Categoria > Data
- Atalhos para títulos próximos ao vencimento (baixa rápida)
- Cards com totais: Receitas, Despesas, Saldo Líquido

> **Cadastros (itens 3 a 6):** arrastar o registro para a direita edita e para a esquerda exclui
> (exclusão lógica, mediante confirmação, restaurável em "Mostrar excluídos"). Categorias, contas
> e formas com vínculos financeiros não podem ser excluídas — devem ser desativadas.

### 3. 💳 Contas Financeiras
- CRUD de contas (corrente, poupança, cartão de crédito, dinheiro, investimento)
- Saldo inicial configurável
- Saldo atual calculado automaticamente com base nas baixas
- Identificação por cor e ícone

### 4. 🏷️ Categorias
- CRUD de categorias de receitas e despesas
- Vinculação de cor e ícone para uso nos gráficos
- Tipo: Receita / Despesa / Ambos

### 5. 👥 Clientes e Fornecedores (Contatos)
- CRUD unificado com tipo: Cliente / Fornecedor / Ambos
- Campos: Nome, CPF/CNPJ, E-mail, Telefone, Endereço completo, Bairro, Cidade, UF, CEP
- Grid de seleção ao incluir título (busca por nome ou código)
- Preenchimento automático dos dados no formulário do título

### 6. 💵 Tipos de Pagamento / Recebimento
- CRUD de formas de pagamento (Dinheiro, PIX, Cartão Débito, Cartão Crédito, Boleto, TED, etc.)
- Tipo: Pagamento / Recebimento / Ambos
- Usados na baixa de títulos (permite múltiplas formas numa mesma baixa)

### 7. 📥 Títulos a Receber
- CRUD completo com:
  - Vinculação a cliente (grid de seleção)
  - Categoria, conta financeira destino
  - Valor original, número de parcelas
  - Datas de emissão e vencimento
  - Status: Pendente / Pago / Vencido / Cancelado / Parcial
  - **Recorrência:** mensal (dia X) ou anual (dia X do mês Y) — ao dar baixa, o próximo vencimento é criado automaticamente. Na inclusão, parcelas ficam fixas em 1 e o primeiro vencimento é calculado a partir da emissão e do dia/mês informados
  - Campos: Referência, Observação
  - Desconto / Juros / Multa ao realizar a baixa
- Listagem com filtros: período, status, categoria, conta, contato
- Baixa rápida via modal (preview + botão baixar + múltiplas formas de pagamento)
- **Atalhos de arraste na listagem:** direita inicia a baixa (abre o formulário no detalhe);
  esquerda cancela o título (sem baixa ativa) ou abre o estorno da última baixa ativa.
  Estorno e cancelamento sempre pedem confirmação em modal; os botões continuam disponíveis.

### 8. 📤 Títulos a Pagar
- Idêntico ao módulo de receber, vinculado a fornecedores
- Mesma lógica de recorrência e baixa

### 9. 🧾 Gerador de Recibos
- Geração de recibo em **PDF** ao realizar a baixa
- Informações: dados do contato, descrição do título, valor, forma(s) de pagamento, data da baixa
- Download e impressão direta

### 10. 🔔 Notificações
- Alertas automáticos para títulos vencendo nos próximos **3, 7 e 15 dias** (configurável)
- Alertas de títulos já **vencidos**
- Sino de notificações no menu com badge de contagem
- Painel de notificações com ação rápida de baixa

### 11. ⚙️ Configurações
- Edição de perfil (nome, e-mail, avatar/foto)
- Preferência persistente de tema (Dark / Light) — **Dark por padrão**
- Configurar dias de antecedência para alertas (0 a 365 dias)
- Importação de títulos via planilha Excel/CSV
- Exportação de relatórios para Excel/CSV/PDF

---

## 🏗️ Decisões de Arquitetura

| Decisão | Escolha | Motivo |
|---|---|---|
| Banco de dados | MySQL 8.0+ / InnoDB | Instalação local disponível, transações e compatibilidade Laravel |
| Frontend | Livewire 3 + MaryUI | Reatividade sem complexidade de SPA |
| Tema padrão | Dark | Preferência do usuário |
| Recibos | PDF (DomPDF) | Download e impressão |
| Títulos recorrentes | Auto-criação na baixa | Simplicidade operacional |
| Baixas com múltiplas formas | `title_settlement_methods` | Flexibilidade real de uso |
| Categorias de títulos | Previsto; estrutura criada | Essencial para gráficos |
| Contas financeiras | CRUD livre | Usuário organiza como preferir |

---

## 📁 Estrutura de Diretórios

Estrutura alvo do repositório (PHP/Laravel). O skeleton, as migrations e os Models financeiros
já existem, assim como `TitleService`, `RecurrenceService`, `CatalogService`,
`FinancialAccountService`, `OverdueTitleService`, `FinancialDefaultsService`,
`app/Support/Money.php` e seeders de categorias/formas.
Demais Services e componentes abaixo ainda são planejados.
Também existem `app/Models/FinancialModel.php`, `app/Models/Scopes/OwnerScope.php`
e `app/Policies/` para isolamento e autorização. Os testes MySQL ficam em `tests/MySql/`,
com configuração independente em `phpunit.mysql.xml`.
A documentação principal do MoneyControl está em `docs/README.md`; o `README.md` da raiz
apresenta o projeto e os links de documentação, mantendo a referência do skeleton Laravel.

```
gestao-financeira/
├── app/
│   ├── Http/
│   │   └── Controllers/         # Controllers (mínimo, maior parte em Livewire)
│   ├── Livewire/
│   │   ├── Auth/                # Componentes de autenticação
│   │   ├── Dashboard/           # Dashboard e gráficos
│   │   ├── Titles/              # CRUD de títulos (receber/pagar)
│   │   ├── Contacts/            # CRUD de clientes e fornecedores
│   │   ├── Accounts/            # CRUD de contas financeiras
│   │   ├── Categories/          # CRUD de categorias
│   │   ├── PaymentTypes/        # CRUD de tipos de pagamento
│   │   ├── Settlements/         # Baixas e recibos
│   │   └── Settings/            # Configurações do sistema
│   ├── Models/
│   │   ├── User.php
│   │   ├── Category.php
│   │   ├── FinancialAccount.php
│   │   ├── Contact.php
│   │   ├── PaymentType.php
│   │   ├── Title.php
│   │   ├── TitleInstallment.php
│   │   ├── TitleSettlement.php
│   │   ├── TitleSettlementMethod.php
│   │   ├── Receipt.php
│   │   └── Notification.php
│   ├── Services/
│   │   ├── TitleService.php     # Lógica de negócio de títulos
│   │   ├── RecurrenceService.php# Lógica de títulos recorrentes
│   │   ├── ReceiptService.php   # Geração de PDF de recibos
│   │   ├── NotificationService.php
│   │   └── ImportExportService.php
│   └── Notifications/
│       ├── TitleDueSoonNotification.php
│       └── TitleOverdueNotification.php
├── database/
│   ├── migrations/              # Migrations em ordem cronológica
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── CategorySeeder.php   # Categorias padrão
│       └── PaymentTypeSeeder.php# Formas de pagamento padrão
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   └── app.blade.php   # Layout principal com sidebar MaryUI
│   │   ├── livewire/           # Views dos componentes Livewire
│   │   └── pdfs/
│   │       └── receipt.blade.php # Template do recibo PDF
│   └── css/
│       └── app.css             # Tailwind CSS
├── routes/
│   ├── web.php                 # Rotas Web
│   └── api.php                 # Rotas API (futuro mobile)
├── docs/
│   ├── DATABASE.md             # Diagrama e estrutura do banco
│   ├── DEVELOPER_GUIDE.md      # Guia completo do desenvolvedor
│   ├── AUDITORIA_SOFTDELETE.md # Auditoria de exclusão lógica por tabela
│   └── AI_DEV_LOG.md           # Log de desenvolvimento por IA
└── README.md                   # Apresentação e links da documentação
```

---

## 🚀 Instalação e Configuração

### Pré-requisitos

- PHP 8.3+
- Extensão PHP `pdo_mysql`; `redis` quando usar `REDIS_CLIENT=phpredis`
- Composer 2.x
- Node.js 20+ e NPM
- MySQL 8.0+ com InnoDB (Wamp local: 8.0.31)
- Redis 7+ para a stack alvo; no ambiente local pode-se usar `database` para cache/sessão/filas

### Passo a passo

Os comandos abaixo são a referência para ambiente novo, com os pré-requisitos disponíveis.
Não sobrescreva um `.env` existente. `DatabaseSeeder` adiciona categorias e formas padrão
aos usuários existentes, sem criar usuários de teste. Para inicializar somente um usuário,
prefira `php artisan finance:initialize <id>`; o usuário precisa existir.
Esses comandos escrevem cadastros e não foram executados no banco local da aplicação nesta etapa.

### Preparar o MySQL Local

Confirme que o serviço MySQL do Wamp está iniciado. O cliente pode estar fora do `PATH`;
na instalação identificada, use no PowerShell:

```powershell
& "C:\wamp64\bin\mysql\mysql8.0.31\bin\mysql.exe" -h 127.0.0.1 -P 3306 -u root -p
```

Informe a senha configurada localmente (Enter se não houver senha). No cliente MySQL,
crie o banco somente se ainda não existir:

```sql
CREATE DATABASE IF NOT EXISTS gestao_financeira
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Configure `.env` conforme o bloco de variáveis abaixo. `root` com senha vazia é apenas
referência local do Wamp, não uma recomendação para produção. Não altere bancos de outros projetos.
Após mudar o `.env`, execute `php artisan config:clear`.
A troca de configuração não transfere dados antigos automaticamente.

Nesta instalação, `gestao_financeira` já foi criado e recebeu as 17 migrations.
Não há usuários de exemplo nem dados financeiros inseridos; os seeders não foram executados.
O banco separado `gestao_financeira_testing` contém a mesma estrutura para os testes MySQL.

### Acesso à Interface Local

Os assets de produção já foram compilados. A chave local ausente foi gerada nesta etapa.
Não gere novamente uma chave existente em ambiente com dados/sessões.

```powershell
php artisan serve
# Abrir http://127.0.0.1:8000
```

Na página inicial, use **Criar minha conta** ou **Acessar**.
Após entrar, `/painel` oferece navegação para receitas, despesas, dashboard financeira, contas, contatos,
categorias, formas, recibos, notificações e preferências. Nos formulários, use vírgula
decimal: `1500,75`. O saldo inicial aceita negativos, como `-10,50`. A conversão para
o formato interno com ponto ocorre antes dos Services, sem cálculos em float.
Contatos podem ser arquivados e restaurados, sempre preservando vínculos financeiros.

### Dashboard Financeira e Relatório

Acesse **Dashboard financeira** no menu. O período começa e termina no mês corrente;
use **Aplicar filtros** para as datas e os chips para incluir/excluir categorias, formas
e contas. Sem chips desmarcados, o resultado é consolidado. Contatos usam seleção múltipla
com busca; sem contato selecionado, todos entram.

Os indicadores usam somente movimentos realizados: cada forma de baixa é registrada na
conta/forma correspondente; estornos entram como valor negativo na data do estorno.
Saldo inicial e títulos ainda pendentes não entram. O botão **Gerar relatório** abre uma
visão tabular profissional para impressão ou salvamento pelo diálogo do navegador, com
cabeçalho do período, cards de entradas/saídas/saldo/lançamentos, agrupamentos claros,
subtotais destacados, total geral, cores financeiras e linhas zebra. A versão impressa
sempre usa página branca, informa o sistema emissor, usuário responsável e data/hora de
emissão, independentemente do tema escuro/claro da aplicação. Não cria um PDF armazenado
no servidor.
Os chips de categoria, forma, conta e contato valem no clique; as datas só entram em vigor
em **Aplicar filtros**. O período aplicado aparece no canto superior da página.
O gráfico de entradas e saídas mantém todos os dias do período, com eixos de valor e data,
e permite consultar data, entradas, saídas e saldo do dia com mouse, toque ou setas do
teclado; o cartão de detalhes acompanha a linha mais alta. **Ver valores por data** lista
somente datas com movimentos, inclusive dias em que baixa e estorno se anulam.
**Histórico de saldo por conta** mostra uma linha por conta financeira com o saldo ao fim
de cada dia, acompanhando os filtros principais de período, conta, categoria, forma e contato.
A abertura preserva o saldo inicial e soma os movimentos anteriores que atendem aos mesmos
filtros; estornos entram na própria data. Com filtros restritivos, o resultado é um
**saldo analítico**, não o saldo bancário completo. Os mini cards abaixo do gráfico,
todos marcados por padrão, apenas escolhem quais linhas exibir e mostram saldo final
e variação no período. As distribuições por
categoria e forma aparecem da maior para a menor participação. Em celulares, os gráficos
rolam na horizontal para manter os eixos legíveis.
Os textos próprios das telas, mensagens financeiras e recibos receberam revisão de
acentuação e ortografia em português brasileiro, sem renomear rotas, campos ou registros
existentes. A tradução integral das mensagens nativas de validação é uma etapa separada.
Campos editáveis de data preservam o seletor de calendário nativo (`type="date"`) e
solicitam o locale brasileiro com `lang="pt-BR"`. O valor continua sendo enviado como
`yyyy-mm-dd` para manter as regras e validações existentes; a apresentação visual
respeita a localização suportada pelo navegador.
**Limitação confirmada:** Chromium em `en-US` ainda mostra mês/dia, apesar do `lang`.
Para exibir/digitar `dd/mm/yyyy` com o mesmo calendário nativo, configure Português
(Brasil) como idioma do navegador e formato regional brasileiro no sistema, reiniciando
o navegador quando solicitado. Não há atributo HTML que force a ordem em todos os
navegadores. A interface não substitui o calendário por campo de texto.

Os formulários monetários exibem decimais com vírgula, inclusive edição, baixa, ajustes
e valores sugeridos para novas formas. `1.234,56` digitado é interpretado como `1234.56`;
nenhum separador de milhar é inserido automaticamente. Valores com mais de duas casas,
negativos onde proibidos ou acima de `DECIMAL(15,2)` continuam sendo rejeitados, sem
arredondamento. Listagens/relatórios já usam `Display::money()` em BRL.
Na lista de receitas/despesas, abra a descrição do título. No detalhe, escolha
**Registrar baixa**, informe principal, ajustes e formas/contas e confirme.
As formas precisam somar principal - desconto + juros + multa. O PDF é emitido
depois, em **Emitir recibo PDF** no histórico. Estorno preserva a baixa e invalida
o documento; cancelamento exige não haver baixas ativas.
Em falha de conexão, confira o histórico e reenvie o mesmo pedido antes de iniciar
outro. Rascunhos/chaves não persistem após recarregar a página.
Antes da baixa, cadastre uma **conta financeira ativa** e uma **forma de pagamento ou
recebimento ativa e compatível**. Usuários novos recebem categorias e formas padrão,
mas não recebem contas fictícias.
Antes de qualquer baixa, títulos sem histórico também podem ter a recorrência mensal/anual
ativada, ajustada ou desativada em **Editar título**. Cadeias já geradas e títulos com
qualquer baixa mantêm a configuração protegida.
O painel mostra contagens de títulos e saldo realizado por conta, não gráficos ou projeções.
O layout é `resources/views/components/layout.blade.php`; os componentes implementados
são `app/Livewire/Catalog.php`, `Titles.php` e `TitleDetail.php`. A árvore de diretórios deste documento contém também
a organização planejada de módulos ainda não implementados.
Para alterar frontend, execute `npm run dev`; para compilar, `npm run build`.
Recuperação de senha local grava a mensagem no log; SMTP real ainda precisa ser configurado.

**Windows (PowerShell)**

```powershell
# 1. Clone o repositório (substitua pela URL do remoto)
git clone <url-do-repositorio> gestao-financeira
cd gestao-financeira

# 2. Instale dependências PHP
composer install

# 3. Instale dependências JS
npm install

# 4. Configure o ambiente
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate

# 5. Crie o banco MySQL, configure credenciais no .env e aplique as migrations
php artisan config:clear
php artisan migrate

# 6. Disponibilize arquivos públicos do storage
php artisan storage:link

# 7. Compile os assets
npm run dev

# 8. Inicie o servidor
php artisan serve
```

**Linux/macOS/WSL/Git Bash**

```bash
# 1. Clone o repositório (substitua pela URL do remoto)
git clone https://github.com/RenanESO/gestao-financeira.git gestao-financeira
cd gestao-financeira

# 2. Instale dependências PHP
composer install

# 3. Instale dependências JS
npm install

# 4. Configure o ambiente
test -f .env || cp .env.example .env
php artisan key:generate

# 5. Configure o banco de dados no .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=gestao_financeira
# DB_USERNAME=seu_usuario
# DB_PASSWORD=
# DB_CHARSET=utf8mb4
# DB_COLLATION=utf8mb4_unicode_ci

# 6. Execute as migrations; inicializacao de cadastros e opcional para usuarios existentes
php artisan config:clear
php artisan migrate

# 6. Disponibilize arquivos públicos do storage
php artisan storage:link

# 7. Compile os assets
npm run dev

# 8. Inicie o servidor
php artisan serve
```

### Verificar a Base Financeira

```powershell
# Suite padrão: SQLite em memória, sem atingir o MySQL local
php artisan test

# Suite MySQL: apenas gestao_financeira_testing, com rollback dos registros de teste
php vendor/bin/phpunit --configuration phpunit.mysql.xml
```

A preparação e as garantias da suite MySQL estão no
[guia do desenvolvedor](DEVELOPER_GUIDE.md#testes-de-integração-mysql).
Ela não executa migrations automaticamente nem apaga tabelas.

### Services Financeiros

Com usuário autenticado, use `app(TitleService::class)->create($dados)`,
`update($titulo, $dados)`, `settle($titulo, $parcela, $dados)` e `cancel($titulo)`.
Valores monetários devem ser strings com ponto decimal (ex.: `'100.10'`), nunca `float`.
Cada baixa exige `idempotency_key` UUID, data, principal (`original_value`) e formas de pagamento.
O principal pode ser parcial, sem exceder o saldo; o total é calculado pelo Service.
Reutilize a mesma chave e os mesmos dados apenas para repetir uma solicitação.
Consulte o contrato e os exemplos no [guia do desenvolvedor](DEVELOPER_GUIDE.md#services-financeiros-entregues).
Não gere UUID novo em cada tentativa de uma mesma baixa.

`FinancialAccountService::balance($conta, $dataOpcional)` retorna saldo inicial,
recebimentos, pagamentos e saldo em strings decimais. Inclui apenas movimentos até a data
e preserva histórico de títulos excluídos logicamente. Não é projeção de títulos pendentes.
`CatalogService` valida campos e proprietário; com histórico, tipos não podem ser alterados,
e saldo inicial da conta fica protegido após o primeiro movimento.

`SettlementReversalService::reverse($baixa, $dados)` exige autenticação, UUID, motivo
e data não anterior à baixa nem futura. Preserva baixa/formas originais e exige nova chave
para uma nova operação. Repetir o mesmo pedido retorna o estorno original.
Nova baixa da parcela não pode anteceder seu último estorno.
Próximo ciclo com histórico e recorrências legadas sem origem rastreável bloqueiam o estorno.
O saldo histórico anterior ao estorno permanece inalterado.
PDF já está implementado por `ReceiptService::generate($baixa)`, somente depois de confirmar
a baixa e sem transação externa. A opção automática `generate_receipt=true` na baixa
continua rejeitada. A emissão é idempotente por baixa; reenvio não muda PDF/snapshot.
Disco `receipts` é privado, sem URLs públicas ou `storage:link`.
Recibos invalidados, estornados, legados sem hash/snapshot e arquivos corrompidos são bloqueados.
Arquivo ausente retorna 404; não há recuperação automática ou substituição silenciosa.

Rotas atuais, com middleware `auth`:

| Método | Rota | Finalidade |
|---|---|---|
| POST | `/baixas/{settlement}/recibo` | Emitir/reobter recibo |
| GET | `/recibos/{receipt}/download` | Download privado autorizado |
| PATCH | `/notificacoes/{notification}/ler` | Marcar leitura |

As telas/login não existem ainda. Hóspedes JSON recebem 401; navegação HTML retorna
à página inicial temporariamente. Downloads não usam links assinados públicos que
possam continuar válidos após estorno.

### Rotinas de Manutenção

```powershell
# Escreve estados de vencidos; opcionalmente limitar ao usuário e data de corte
php artisan titles:update-overdue --user=1 --date=2026-10-15

# Escreve apenas categorias/formas ausentes de um usuário existente
php artisan finance:initialize 1

# Criar alertas internos de vencidos e proximos, sem duplicar reexecucoes
php artisan notifications:send-due-soon --user=1

# Mostra a agenda, sem executar as rotinas
php artisan schedule:list
```

Substitua o ID pelo usuário correto. Sem `--user`, o comando de vencidos processa todos
os usuários por transações separadas. Em desenvolvimento, `php artisan schedule:work`
mantém o scheduler ativo; em produção, configurar `schedule:run` a cada minuto.
A agenda de vencidos roda às 00:05 e a de alertas às 08:00 no fuso da aplicação (atualmente UTC),
com `withoutOverlapping()`. Nenhum serviço do Windows foi configurado automaticamente.
Antecedência das notificações: preferência do usuário, padrão 7 dias; aceita 0–365 na tela de preferências.
Alertas são internos ao banco, não e-mail/push/SMS. A tela `/notificacoes` lista o histórico
e permite atualizar os alertas imediatamente, além de marcar leitura individual ou em lote.
O agendamento às 08:00 só é automático enquanto `php artisan schedule:work` estiver
ativo localmente ou `schedule:run` for acionado a cada minuto em produção.
DomPDF (`barryvdh/laravel-dompdf` 3.1.2 e `dompdf/dompdf` 3.1.6) está no lock de produção;
em outra máquina, `composer install` instala as versões previstas.

---

## 🧷 Repositório Git

Esta seção descreve o fluxo recomendado para inicialização do repositório, criação de `.gitignore` e configuração de remoto. Não execute até que a base do projeto esteja pronta.

### Inicialização local

```bash
git init
git add .
git commit -m "chore: inicializa repositório"
```

### Configuração de remoto

```bash
git remote add origin https://github.com/RenanESO/gestao-financeira.git
git branch -M main
git push -u origin main
```

### `.gitignore` recomendado (Laravel/PHP)

```gitignore
/vendor/
/node_modules/
/public/hot
/public/storage
/storage/*.key
/storage/app/public/*
!/storage/app/public/.gitignore
/storage/framework/cache/*
/storage/framework/sessions/*
/storage/framework/views/*
!/storage/framework/cache/.gitignore
!/storage/framework/sessions/.gitignore
!/storage/framework/views/.gitignore
/storage/logs/*
!/storage/logs/.gitignore
/.env
/.env.*
!.env.example
/.phpunit.result.cache
/.idea/
/.vscode/
.DS_Store
Thumbs.db
```

### `composer.json` mínimo (modelo)

```json
{
  "name": "moneycontrol/gestao-financeira",
  "description": "MoneyControl — sistema web de gestão financeira pessoal (Laravel + Livewire + maryUI).",
  "type": "project",
  "license": "proprietary",
  "require": {
    "php": "^8.3"
  },
  "autoload": {
    "psr-4": {
      "App\\\\": "app/"
    }
  },
  "config": {
    "sort-packages": true
  }
}
```

---

## 🔧 Variáveis de Ambiente Importantes

- Use `.env.example` como referência e mantenha-o atualizado.
- Nunca coloque senhas/tokens reais em documentação. O arquivo `.env` é local e não deve ser versionado.

```env
APP_NAME="MoneyControl"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestao_financeira
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=log

# A antecedência é persistida em users.notification_days_before (padrão: 7).
# A configuração dos alertas 3/7/15 dias será implementada no módulo de notificações.
```

O bloco acima usa Redis como stack alvo, assim como `.env.example`. Para o ambiente local
sem Redis, mantenha `CACHE_STORE=database`, `SESSION_DRIVER=database` e
`QUEUE_CONNECTION=database`, como no `.env` atual. Essas opções exigem as migrations
de infraestrutura; filas em `database` também precisam de `php artisan queue:work`.

### Deploy futuro (hospedagem compartilhada)

- Definir o document root para a pasta `public/`.
- Garantir permissões de escrita em `storage/` e `bootstrap/cache/`.
- Rodar `php artisan storage:link` no servidor para expor `storage/app/public` em `public/storage` (se o provedor suportar symlink).
- Caso não haja Redis/queue/scheduler no plano, ajustar `CACHE_STORE` e `SESSION_DRIVER` para `file` e `QUEUE_CONNECTION` para `sync` ou `database`, documentando as limitações. `file` não é um driver de filas.

---

## 📏 Convenções do Projeto

- **PSR-12** para formatação de código PHP
- **Português** nos nomes de variáveis do domínio (ex: `$titulo`, `$parcela`) e **inglês** nos nomes de tabelas/colunas do banco
- Componentes Livewire em `PascalCase`, um por funcionalidade principal
- Sempre usar **transactions** do banco em operações de baixa (consistência financeira)
- Regras de negócio centralizadas em `Services/`, nunca em Controllers ou Livewire diretamente
- Testes em `tests/Feature/` para cada módulo financeiro crítico
- Models financeiros usam `OwnerScope`; sem autenticação, as consultas retornam vazias.
- IDs de proprietário e de relacionamentos não são mass assignable. Use relacionamentos
  após validação e autorização; consulte o guia antes de implementar Services ou comandos.

---

## 📞 Suporte e Documentação Adicional

- [`DATABASE.md`](DATABASE.md) — Diagrama ER e estrutura completa do banco
- [`DEVELOPER_GUIDE.md`](DEVELOPER_GUIDE.md) — Guia detalhado de desenvolvimento
- [`AUDITORIA_SOFTDELETE.md`](AUDITORIA_SOFTDELETE.md) — Auditoria de SoftDelete por tabela, exceções e pendências
- [`AI_DEV_LOG.md`](AI_DEV_LOG.md) — Log de desenvolvimento pelos agentes de IA

**Referências externas**

- Laravel: https://laravel.com/docs
- Livewire: https://livewire.laravel.com/docs
- maryUI: https://mary-ui.com/docs
- Tailwind CSS: https://tailwindcss.com/docs
- MySQL: https://dev.mysql.com/doc/refman/8.0/en/
- Redis: https://redis.io/docs/latest/

---

*Projeto em desenvolvimento ativo — versão inicial gerada por agente IA em 14/10/2026.*
