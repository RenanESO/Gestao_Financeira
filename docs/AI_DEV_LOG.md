# 🤖 Log de Desenvolvimento — Agentes de IA

> **Projeto:** MoneyControl — Sistema de Controle Financeiro Pessoal  
> **Objetivo deste arquivo:** Registrar cronologicamente todas as ações, decisões e entregas realizadas pelos agentes de IA ao longo do desenvolvimento. Serve como rastreabilidade e histórico para o desenvolvedor humano.

---

## 📋 Formato de Entrada

```
### [DATA] — [AGENTE] — [MÓDULO/ÁREA]
**Ação:** O que foi feito
**Decisões:** Escolhas técnicas tomadas e por quê
**Arquivos:** Lista de arquivos criados/modificados
**Pontos de atenção:** Alertas para o desenvolvedor
**Próximos passos:** O que ainda precisa ser feito nessa área
```

---

## 📅 Histórico de Desenvolvimento

---

### [14/10/2026] — Abacus AI Agent (DeepAgent) — FASE 0: Planejamento Inicial

**Ação:** Coleta de requisitos e planejamento completo do sistema com o usuário.

**Requisitos levantados e confirmados:**
- ✅ Autenticação via Laravel Breeze
- ✅ Dashboard com gráficos de receitas/despesas por período (dia/mês/ano)
- ✅ MaryUI + Tailwind CSS como stack de frontend
- ✅ Tema dark por padrão, com alternância light/dark
- ✅ CRUD de Títulos a Receber e a Pagar
- ✅ CRUD de Clientes e Fornecedores (tabela unificada `contacts`)
- ✅ CRUD de Categorias de receitas e despesas
- ✅ CRUD de Contas Financeiras (múltiplas: corrente, poupança, cartão, etc.)
- ✅ CRUD de Tipos de Pagamento/Recebimento (múltiplas formas numa mesma baixa)
- ✅ Sistema de parcelamentos de títulos
- ✅ Títulos recorrentes mensais/anuais — auto-criação na baixa (sem data de fim)
- ✅ Sistema de notificações para vencimentos próximos
- ✅ Baixa de títulos via modal rápido, com múltiplas formas de pagamento
- ✅ Gerador de recibos em PDF (DomPDF) para títulos baixados
- ✅ Importação e exportação de planilhas (Laravel Excel)
- ✅ Configurações de perfil (nome, e-mail, avatar)

**Decisões de arquitetura confirmadas:**
- **Banco de dados:** PostgreSQL 16+ — escolhido por suporte a JSON, performance em relatórios, compatibilidade com futuro mobile
- **ORM:** Laravel Eloquent com Global Scopes para isolamento por `user_id`
- **Camada de negócio:** Service Layer (`app/Services/`) — regras nunca em Controllers ou Livewire
- **Operações críticas:** Sempre dentro de `DB::transaction()`
- **Soft delete:** Aplicado em `titles` e `contacts`
- **Números sequenciais:** `REC-0001` (receber) e `PAG-0001` (pagar) gerados no Service
- **Recibos:** Formato PDF com template Blade + DomPDF, armazenado em `storage/app/receipts/{user_id}/`

**Arquivos criados:**
- `README.md` — Documentação principal do projeto
- `docs/DATABASE.md` — Diagrama ER Mermaid + estrutura completa de 11 tabelas
- `docs/DEVELOPER_GUIDE.md` — Guia completo com código de exemplo para cada módulo
- `docs/AI_DEV_LOG.md` — Este arquivo

**Tabelas do banco definidas:**

| Tabela | Responsabilidade |
|---|---|
| `users` | Auth Breeze + tema + preferências |
| `categories` | Categorias de receitas/despesas |
| `financial_accounts` | Contas financeiras do usuário |
| `contacts` | Clientes e fornecedores unificados |
| `payment_types` | Formas de pagamento e recebimento |
| `titles` | Títulos a receber e a pagar (central) |
| `title_installments` | Parcelas dos títulos |
| `title_settlements` | Registros de baixas |
| `title_settlement_methods` | Formas de pagamento de cada baixa |
| `receipts` | Recibos PDF gerados |
| `notifications` | Alertas do sistema |

**Seeders planejados:**
- `CategorySeeder` — 12 categorias padrão (4 receitas, 8 despesas)
- `PaymentTypeSeeder` — 7 formas de pagamento padrão (Dinheiro, PIX, Cartão Débito, Crédito, Boleto, TED/DOC, Cheque)

**Pontos de atenção para o desenvolvedor:**
- ⚠️ Toda query deve filtrar por `user_id = auth()->id()` — dados são isolados por usuário
- ⚠️ A soma das formas de pagamento em `title_settlement_methods` deve ser sempre igual a `title_settlements.total_value`
- ⚠️ Títulos recorrentes: ao dar baixa, o `RecurrenceService` cria o próximo vencimento automaticamente
- ⚠️ Status "vencido" é atualizado por um comando agendado (`titles:update-overdue`) — não calcular na query
- ⚠️ O `parent_title_id` em recorrências **sempre aponta para o título original** (raiz), não para o anterior

**Próximos passos sugeridos (FASE 1 — Implementação):**
1. Configurar projeto Laravel 12 com Breeze, MaryUI e Tailwind
2. Criar migrations em ordem (users → categories → accounts → contacts → payment_types → titles → installments → settlements → methods → receipts → notifications)
3. Criar Models com relacionamentos Eloquent
4. Criar Services com regras de negócio
5. Implementar commands agendados (overdue, notifications)
6. Criar Seeders de dados iniciais
7. Implementar layouts MaryUI (sidebar, header, tema dark)
8. Implementar Dashboard (KPIs e gráficos)
9. Implementar CRUDs (categories, accounts, contacts, payment_types)
10. Implementar Títulos (CRUD + parcelamento + recorrência)
11. Implementar Modal de Baixa (múltiplas formas de pagamento)
12. Implementar Gerador de Recibos PDF
13. Implementar Sistema de Notificações
14. Implementar Import/Export de planilhas

---

## 🚧 Backlog / Funcionalidades Futuras

> Registre aqui ideias e funcionalidades para implementar futuramente, sem bloquearem o desenvolvimento atual.

| ID | Funcionalidade | Prioridade | Observação |
|---|---|---|---|
| F-001 | App Mobile (React Native / Flutter) | Alta | Banco PostgreSQL já preparado para API REST |
| F-002 | Integração com Open Banking | Média | Importar extratos automaticamente |
| F-003 | Metas financeiras mensais | Média | Ex: "Limite R$ 500 em Alimentação" |
| F-004 | Relatórios avançados em PDF | Média | DRE simplificado, fluxo de caixa |
| F-005 | Dashboard de investimentos | Baixa | Integração com B3 / corretoras |
| F-006 | Multi-usuário familiar | Baixa | Compartilhar contas com cônjuge/família |
| F-007 | Reconhecimento de boleto por câmera | Baixa | Importar dados do boleto via foto |

---

## ✅ Checklist de Marcos do Projeto

- [x] FASE 0 — Planejamento e Documentação Inicial (14/10/2026)
- [ ] FASE 1 — Configuração do Projeto e Banco de Dados
- [ ] FASE 2 — Autenticação e Layout Principal
- [ ] FASE 3 — CRUDs Base (Categorias, Contas, Contatos, Tipos Pagamento)
- [ ] FASE 4 — Módulo de Títulos (CRUD + Parcelamento + Recorrência)
- [ ] FASE 5 — Sistema de Baixas e Recibos PDF
- [ ] FASE 6 — Dashboard e Gráficos
- [ ] FASE 7 — Sistema de Notificações
- [ ] FASE 8 — Import/Export de Planilhas
- [ ] FASE 9 — Configurações e Perfil
- [ ] FASE 10 — Testes e Refinamentos
- [ ] FASE 11 — Deploy em Produção

---

*Log iniciado em 14/10/2026 pelo Abacus AI Agent.*  
*Adicione novas entradas ao final deste arquivo a cada sessão de desenvolvimento.*

---

### [2026-10-15] — Assistente de IA — FASE 1: Estrutura Financeira do Banco

**Data:** 2026-10-15
**Autor:** Assistente de IA
**O que foi feito:** Leitura integral dos cinco documentos de `docs/`, conferência do skeleton
e continuidade das migrations existentes. Instalação de dependências concluída sem cache,
contornando a falha de permissão registrada em `composer-install.log`. Estrutura financeira
implementada e validada em banco SQLite isolado. Preferências do usuário alinhadas ao projeto.

**Arquivos de código alterados/criados:**
- `database/migrations/2026_10_15_000001_add_profile_fields_to_users_table.php`
- `database/migrations/2026_10_15_000002_create_categories_table.php`
- `database/migrations/2026_10_15_000003_create_financial_accounts_table.php`
- `database/migrations/2026_10_15_000004_create_contacts_table.php`
- `database/migrations/2026_10_15_000005_create_payment_types_table.php`
- `database/migrations/2026_10_15_000006_create_titles_table.php`
- `database/migrations/2026_10_15_000007_create_title_installments_table.php`
- `database/migrations/2026_10_15_000008_create_title_settlements_table.php`
- `database/migrations/2026_10_15_000009_create_title_settlement_methods_table.php`
- `database/migrations/2026_10_15_000010_create_receipts_table.php`
- `database/migrations/2026_10_15_000011_create_notifications_table.php`
- `app/Models/User.php`
- `.env.example`
- `phpunit.xml`
- `tests/Unit/UserPreferencesTest.php`
- `tests/Feature/FinancialSchemaTest.php`
- `tests/Feature/MigrationLifecycleTest.php`

**Documentos atualizados:** `docs/README.md`, `docs/DEVELOPER_GUIDE.md`,
`docs/DATABASE.md`, `docs/AI_DEV_LOG.md`. `REGRAS_DOCUMENTACAO.md` foi relido e mantido intacto.
Foram removidas duplicações de setup, corrigidos links relativos, indicação de base não
inicializada, uso de `CACHE_DRIVER`, caminho de agendamento do Laravel 12 e exemplo de consulta
de título sem filtro de usuário. Exemplos dos módulos ainda são referências, não código entregue.

**Impacto no banco:** Onze migrations adicionam perfil e criam dez tabelas de domínio,
com FKs restritivas, `DECIMAL(15,2)`, enums, índices e soft deletes em contatos/títulos.
Códigos de contatos e números de títulos são únicos por usuário; parcelas são únicas por
título/número; recibos têm número globalmente único e um registro por baixa.
Índices GIN de contatos e parciais de notificações/parcelas são exclusivos do PostgreSQL.
O diagrama e as tabelas da documentação foram alinhados, inclusive timestamps anuláveis.
Nenhuma migration foi aplicada ao banco local ou de produção; somente ao SQLite em memória.

**Verificações:** `php artisan test`: **37 testes aprovados, 88 assertions** (35 novos casos
e os dois exemplos do skeleton). Foram verificados defaults, casts, credenciais ocultas,
persistência da cadeia financeira, enums inválidos, unicidade, FKs, exclusões restritas,
histórico após exclusão lógica, rollback transacional e aplicação/reversão/reaplicação das migrations.
Pint aplicado aos arquivos PHP da entrega. O `.env` local e o histórico anterior foram preservados.

**Limites / pontos de atenção:**
- PHP disponível: 8.2.0; requisito alvo permanece 8.3+. `composer.json` ainda declara o requisito do skeleton `^8.2`.
- PostgreSQL/Redis não foram encontrados e as extensões `pdo_pgsql`/`phpredis` não estão habilitadas; não houve validação dos índices PostgreSQL, da precisão decimal nesse banco ou de concorrência.
- Breeze/MaryUI estão instalados como dependências de desenvolvimento, sem scaffolding; Livewire 3 está ausente e Tailwind 4 do skeleton ainda deve ser alinhado ao Tailwind 3 especificado.
- FKs não validam propriedade dos vínculos nem soma/fórmula de baixa. Models, Policies e Services devem implementar essas garantias antes de qualquer operação pela UI.
- A tabela customizada de notificações não é compatível diretamente com o canal `database` nativo; integração pendente.
- O diretório atual não é um repositório Git; não foram criados repositório, commits ou remotos.

**Pendências / próximos passos (ordem de implementação):**
1. Concluir a configuração da stack e validar migrations/índices em PostgreSQL de desenvolvimento, sem apagar bancos existentes.
2. Criar Models e relacionamentos Eloquent, casts decimais, soft deletes, isolamento por usuário e testes de autorização cruzada.
3. Criar Services com regras de negócio e validações, incluindo propriedade de vínculos, centavos, concorrência e recorrência.
4. Implementar comandos e seeders iniciais; depois autenticação, layout e módulos na ordem registrada.

**Checklist desta etapa:**
- [x] Migrations criadas, aplicadas e revertidas em SQLite de teste.
- [x] Testes unitários e de integração aprovados.
- [x] Diagrama, guia, documento inicial e histórico atualizados.
- [ ] Validar banco PostgreSQL e concluir configuração da stack.
- [ ] Concluir a FASE 1 completa antes de marcar o marco geral como finalizado.

---

### [2026-10-15] — Assistente de IA — Troca do Banco Principal para MySQL

**Data:** 2026-10-15
**Autor:** Assistente de IA
**O que foi feito:** Banco principal alterado de PostgreSQL para MySQL 8.0+ por solicitação
do usuário, aproveitando a instalação local. MySQL 8.0.31 localizado no Wamp com serviço
em execução; `pdo_mysql` habilitado no PHP. Conexão somente de leitura verificada em
`127.0.0.1:3306`. O banco `gestao_financeira` ainda não existe.

**Decisões:** Usar InnoDB, modo estrito, charset `utf8mb4` e collation
`utf8mb4_unicode_ci`. Futuro mobile consumirá uma API Laravel, independentemente do banco.
MySQL passa a substituir a decisão inicial e as pendências PostgreSQL registradas acima,
inclusive a observação do backlog F-001. Entradas anteriores foram preservadas como histórico.

**Arquivos de código alterados:**
- `.env` (apenas parâmetros do banco; demais configurações e chave preservadas)
- `.env.example`
- `config/database.php`
- `database/migrations/2026_10_15_000004_create_contacts_table.php`
- `database/migrations/2026_10_15_000007_create_title_installments_table.php`
- `database/migrations/2026_10_15_000011_create_notifications_table.php`
- `tests/Feature/FinancialSchemaTest.php`

**Documentos atualizados:** `README.md`, `docs/README.md`, `docs/DATABASE.md`,
`docs/DEVELOPER_GUIDE.md` e `docs/AI_DEV_LOG.md`. Regras de documentação relidas e preservadas.
Atualizados requisitos, justificativa do banco, configuração, tipos MySQL, exemplos SQL,
busca `LIKE` e preparação do banco local.

**Impacto no banco:** Nenhum banco local criado, apagado ou alterado; nenhum dado transferido.
Migrations iniciais adaptadas: GIN de contatos substituído por índices B-tree
`(user_id, name)` e `(user_id, document)`; parcelas usam índice composto
`(title_id, status, due_date)` sem filtro parcial; notificações usam o índice existente
`(user_id, read_at)` sem duplicação. Os demais drivers padrão do Laravel permanecem
disponíveis na configuração, mas não são a conexão principal.

**Verificações:** `php artisan test`: **38 testes aprovados, 92 assertions** em SQLite
em memória, com criação/reversão/reaplicação das migrations e novo teste dos índices.
`php vendor/bin/pint --test config/database.php database/migrations tests/Feature/FinancialSchemaTest.php`:
aprovado em 16 arquivos. `php artisan config:clear` concluído.
Consulta MySQL de versão e existência do banco concluída sem escrita.

**Pendências / próximos passos:**
1. Criar `gestao_financeira`, conferir credenciais locais e executar `php artisan migrate` conforme o README.
2. Validar migrations, índices, `DECIMAL`, enums, collation e concorrência em MySQL; SQLite não substitui essa validação.
3. Se outro ambiente já executou as migrations iniciais, criar migration incremental para atualizar os índices, sem usar comandos que apaguem dados.
4. Continuar o alinhamento PHP 8.3+/frontend e configuração Redis. O `.env` local mantém cache/sessão/filas em `database`; `.env.example` mantém Redis como alvo.

---

### [2026-10-15] — Assistente de IA — FASE 1: MySQL, Models e Autorização

**Data:** 2026-10-15
**Autor:** Assistente de IA
**O que foi feito:** Leitura dos cinco documentos de `docs/` e continuidade pela base
financeira. Criado `gestao_financeira` no MySQL 8.0.31 e aplicadas as 14 migrations
pendentes, sem reset, rollback ou exclusão de dados locais. Criado separadamente
`gestao_financeira_testing`, com a mesma estrutura, para integração transacional.

Implementados dez Models financeiros, relacionamentos diretos/inversos, casts monetários
`decimal:2`, datas, flags e inteiros. `Contact` e `Title` usam soft delete. Relacionamentos
históricos a contato, título e raiz da recorrência incluem registros excluídos logicamente
sem remover a restrição de proprietário.

**Arquivos de código criados:**
- `app/Models/FinancialModel.php`
- `app/Models/Scopes/OwnerScope.php`
- `app/Models/Category.php`
- `app/Models/FinancialAccount.php`
- `app/Models/Contact.php`
- `app/Models/PaymentType.php`
- `app/Models/Title.php`
- `app/Models/TitleInstallment.php`
- `app/Models/TitleSettlement.php`
- `app/Models/TitleSettlementMethod.php`
- `app/Models/Receipt.php`
- `app/Models/Notification.php`
- `app/Policies/FinancialRecordPolicy.php`
- `app/Policies/TitlePolicy.php`
- `tests/Feature/FinancialModelsTest.php`
- `tests/MySql/MySqlFinancialTest.php`
- `phpunit.mysql.xml`

**Arquivos de código alterados:** `app/Models/User.php` (relacionamentos) e
`app/Providers/AppServiceProvider.php` (registro explícito das Policies).
Nenhuma dependência, rota ou tela foi adicionada; `.env` e credenciais foram preservados.

**Decisões / segurança:**
- `OwnerScope` restringe consultas ao autenticado; sem identidade, retorna vazio. Parcelas
  e baixas usam o dono do título; formas usam o dono do título associado à baixa.
- IDs e FKs não são mass assignable. Persistência de vínculos deve usar relacionamentos,
  depois de validação/autorização. O escopo não autoriza `save()` em objetos previamente
  carregados nem valida propriedade de todos os vínculos: isso exige Service e Gate.
- Policies verificam propriedade persistida, inclusive em objetos carregados sem escopo.
  Títulos/contatos permitem soft delete e restauração ao dono; exclusão física é negada.
  Parcelas, baixas, formas e recibos não têm edição/exclusão CRUD genérica via Policy.
- O relacionamento customizado é `User::financialNotifications()`, separado dos métodos
  nativos de `Notifiable`. A integração do canal de notificações continua pendente.
- Jobs/comandos precisarão de proprietário explícito e remoção controlada de `OwnerScope`
  quando apropriado. Os exemplos no guia foram ajustados aos IDs protegidos.

**Documentos atualizados:** `docs/README.md`, `docs/DATABASE.md`,
`docs/DEVELOPER_GUIDE.md` e `docs/AI_DEV_LOG.md`. Atualizados estado real, relacionamentos,
limites, exemplos, próxima sequência e instruções da suite MySQL. Regras relidas e preservadas.

**Impacto no banco:** Criados dois bancos e aplicadas as migrations já existentes.
Schema MySQL verificado: 19 tabelas InnoDB/`utf8mb4_unicode_ci`, 19 FKs com `RESTRICT`,
índices de contatos/parcelas/notificações e tipos monetários/enums. Não foram executados
seeders, nem criados usuários ou dados financeiros no banco da aplicação. Os registros
de integração no banco exclusivo de testes são revertidos após cada teste.

**Verificações:**
- `php artisan test`: **96 testes aprovados, 534 assertions** em SQLite em memória.
- `php vendor/bin/phpunit --configuration phpunit.mysql.xml`: **9 testes aprovados,
  116 assertions** em MySQL, cobrindo schema, decimais exatos e limite de precisão,
  rejeição de overflow/enums, unicidade por collation, FKs e consultas de proprietário.
- `php artisan migrate:status`: 14 migrations aplicadas no banco local.
- Pint aplicado aos Models, Policies, provider e testes.
- Concorrência, Services e UI ainda não foram testados nem implementados nesta etapa.

**Pendências / próximos passos:**
1. Concluir alinhamento PHP 8.3+, Livewire 3, Tailwind 3 e dependências de produção. A máquina continua com PHP 8.2.0; nenhum runtime foi instalado ou reconfigurado.
2. Implementar Services com autorização e validação de vínculos, centavos exatos, saldo restante, numeração concorrente e recorrência. Os exemplos do guia não são código de produção.
3. Criar comandos e seeders financeiros; depois autenticação, layout e módulos na ordem do planejamento.
4. Configurar Redis quando disponível; manter drivers `database` locais enquanto isso.
5. A FASE 1 geral permanece em andamento; Models/MySQL concluídos não significam stack completa ou sistema pronto.

---

### [2026-10-15] — Assistente de IA — Services Financeiros, Baixas e Recorrência

**Data:** 2026-10-15
**Autor:** Assistente de IA
**O que foi feito:** Continuidade pela camada de negócio, seguindo `docs/`.
Implementados `TitleService::create()`, `settle()` e `cancel()`, o cálculo puro
`RecurrenceService::nextDueDate()` e o utilitário `Money`.

Criação valida dono, vínculos, atividade/tipo, datas, valores e recorrência. Gera 1–120
parcelas com soma exata em centavos, distribuindo o resto nas primeiras e preservando
o dia original em meses curtos. Recorrência utiliza uma parcela por ciclo.

Baixas validam principal/saldo, desconto, juros, multa, total e 1–20 formas. Valores são
strings decimais ou inteiros em unidades monetárias; `float`, negativos, excesso de casas
e overflow são rejeitados. `original_value` representa o principal liquidado na baixa.
Desconto/juros/multa compõem o total, não o saldo de principal da parcela.

Todas as mutações exigem usuário autenticado, recarregam os registros com filtro do dono
e usam transação e bloqueio na ordem usuário/título/parcelas. Numeração inclui soft deletes
e usa maior sufixo, não `count() + 1`, com lock do usuário até o commit.
Leituras críticas também usam `lockForUpdate()`. O Laravel pode repetir a transação até
três vezes em erros de concorrência reconhecidos; testes entre processos independentes
ainda precisam ser adicionados.

Baixas totais/parciais exigem UUID de idempotência. Mesmo pedido normalizado retorna a mesma
baixa; chave reutilizada com dados/parcela diferentes é rejeitada. O hash inclui ordem
das formas e observações. Nova chave significa nova operação e não deve ser gerada
automaticamente em reenvios de rede.

Recorrência é gerada pelo `TitleService` somente após liquidação integral, na mesma
transação, apontando diretamente à raiz. Datas usam o ciclo nominal anterior e limitam o
dia ao mês, preservando a configuração para ciclos seguintes e anos bissextos. Falha ao
gerar o próximo ciclo reverte também a baixa. Cancelamento sem baixas altera estados sem
apagar dados; títulos já liquidados parcialmente exigirão estorno e não podem ser cancelados.

**Arquivos de código criados:**
- `app/Support/Money.php`
- `app/Services/TitleService.php`
- `app/Services/RecurrenceService.php`
- `database/migrations/2026_10_15_000012_add_idempotency_to_title_settlements_table.php`
- `tests/Concerns/TitleServiceScenarios.php`
- `tests/Feature/TitleServiceTest.php`
- `tests/MySql/MySqlTitleServiceTest.php`
- `tests/Unit/MoneyTest.php`
- `tests/Unit/RecurrenceServiceTest.php`

**Arquivos de código alterados:** `app/Models/TitleSettlement.php` (hash oculto na
serialização, IDs/hash permanecem protegidos de mass assignment) e
`tests/Feature/MigrationLifecycleTest.php` (reversão das 12 migrations financeiras em SQLite).

**Documentos atualizados:** `docs/README.md`, `docs/DATABASE.md`,
`docs/DEVELOPER_GUIDE.md` e `docs/AI_DEV_LOG.md`. Incluídos contrato, exemplos reais,
limites, estados, idempotência e calendário. Removidos exemplos antigos de cálculo
com float, numeração por contagem e recorrência baseada em hoje. Regras relidas.

**Impacto no banco:** Uma migration incremental adiciona `idempotency_key` CHAR(36)
e `request_hash` CHAR(64), anuláveis para preservar baixas antigas, com
`UNIQUE(title_id, idempotency_key)`. Sem backfill ou alteração de valores históricos.
Aplicada nos dois bancos locais: agora 15 migrations, mantendo 19 tabelas e 19 FKs
restritivas. Colunas e índice único verificados via `information_schema`.
Nenhum seeder executado. Usuários/títulos/baixas continuam vazios na aplicação e nos
testes MySQL após rollback; nenhum banco foi apagado.

**Verificações:**
- `php artisan test --compact`: **170 testes aprovados, 677 assertions** em SQLite.
- `php vendor/bin/phpunit --configuration phpunit.mysql.xml`: **61 testes aprovados,
  331 assertions** em MySQL exclusivo de testes, com rollback por cenário.
- Cobertura: centavos, parcelas, datas inválidas, vínculos cruzados, estados, principal
  parcial/restante, ajustes, idempotência parcial/total, anos bissextos, cancelamento,
  falhas após gravação e falhas ao criar recorrência.
- Pint aprovado em 47 arquivos PHP; 15 migrations locais confirmadas.

**Pendências / próximos passos:**
1. Validar concorrência entre processos MySQL independentes e cenários de carga; os testes desta etapa são sequenciais/transacionais.
2. Implementar edição controlada, saldo de contas, estorno, recibos e importação/exportação. PDF não disponível: `generate_receipt=true` é rejeitado explicitamente.
3. Implementar comandos e seeders, depois autenticação, layouts e módulos. Services ainda não estão expostos por telas ou rotas.
4. Concluir alinhamento PHP 8.3+, Livewire 3, Tailwind 3 e dependências de produção; Redis permanece pendente. Runtime, dependências e credenciais não foram alterados nesta etapa.
5. Adaptar futuras rotinas sem sessão para identidade explícita e validada; não remover escopos na interface. A FASE 1 geral segue em andamento.

---

### [2026-10-15] — Assistente de IA — Saldo, Cadastros, Edição e Rotinas de Manutenção

**Data:** 2026-10-15
**Autor:** Assistente de IA
**O que foi feito:** Retomada pelo `STATUS_DESENVOLVIMENTO.md`, com leitura das regras e
regressão inicial. Entregue o bloco de saldo realizado por conta/data, cadastros financeiros,
edição controlada de títulos, atualização de vencidos e inicialização dos cadastros padrão.

`FinancialAccountService::balance()` soma valores das formas recebidas/pagas até a data,
com abertura e saldo assinados. Inclui histórico de títulos excluídos logicamente e contas
inativas. Totais são exatos em centavos e rejeitam overflow; não é previsão de títulos pendentes.
O corte por data foi validado nos dois bancos, considerando a diferença de armazenamento
de DATE pelo SQLite. A precisão máxima foi verificada especificamente no MySQL.

`CatalogService` cria/edita categorias, contas, contatos e formas, com validação de campos,
proprietário, Gate e lock do usuário. Gera códigos de contatos por prefixo/proprietário,
sem reutilizar códigos de soft deletes. Oferece arquivamento/restauração de contatos.
Tipos ficam protegidos após vínculos, e abertura de contas após movimentos; nomes/atividade
podem ser atualizados. Nenhum fluxo de exclusão física foi adicionado.

`TitleService::update()` mantém identidade, tipo, quantidade e IDs das parcelas. Sem baixas,
recalcula valor/datas e permite vínculos validados; com baixas, aceita somente metadados.
Títulos cancelados e mudanças de recorrência em cadeias geradas são bloqueados.
Falhas durante edição das parcelas revertem toda a operação. O fluxo existente de
criação/baixa/recorrência permanece coberto pela regressão.

`OverdueTitleService` processa proprietário explícito sem modificar autenticação. O comando
`titles:update-overdue` aceita usuário/data, processa usuários por transações separadas
e mantém estados pagos/parciais/cancelados e soft deletes. Agenda às 00:05 com
`withoutOverlapping()`. O scheduler ainda precisa ser iniciado/configurado; o fuso atual é UTC.

`FinancialDefaultsService` e `finance:initialize` completam categorias/formas ausentes.
Seeders foram implementados e `DatabaseSeeder` deixou de criar usuário de demonstração.
Não sobrescrevem registros existentes nem criam contas, saldos fictícios ou títulos.
Nomes padrão renomeados podem ser recriados numa reexecução; não há identificador imutável de padrão.

**Arquivos de código criados:**
- `app/Services/FinancialAccountService.php`
- `app/Services/CatalogService.php`
- `app/Services/OverdueTitleService.php`
- `app/Services/FinancialDefaultsService.php`
- `app/Console/Commands/UpdateOverdueTitles.php`
- `app/Console/Commands/InitializeFinancialDefaults.php`
- `config/financial_defaults.php`
- `database/seeders/CategorySeeder.php`
- `database/seeders/PaymentTypeSeeder.php`
- `tests/Concerns/FinancialOperationsScenarios.php`
- `tests/Feature/FinancialOperationsTest.php`
- `tests/MySql/MySqlFinancialOperationsTest.php`

**Arquivos de código alterados:** `app/Services/TitleService.php`,
`app/Support/Money.php`, `database/seeders/DatabaseSeeder.php`, `routes/console.php`
e `tests/Unit/MoneyTest.php`.

**Documentos atualizados:** `docs/STATUS_DESENVOLVIMENTO.md`, `docs/README.md`,
`docs/DEVELOPER_GUIDE.md`, `docs/DATABASE.md` e `docs/AI_DEV_LOG.md`.
O status registra o ponto entregue, contratos, verificação e próxima sequência;
o histórico anterior foi preservado e regras relidas.

**Impacto no banco:** Nenhuma migration nova, alteração de schema ou escrita no banco
da aplicação. Mantidas 15 migrations, 19 tabelas e 19 FKs restritivas.
Testes de Services, comandos e seeders foram executados em SQLite em memória e MySQL
exclusivo de integração com rollback. Consultas finais confirmaram zero usuários,
categorias, formas, títulos e baixas nos dois bancos locais. Não foram executados resets,
seeders locais nem comandos de escrita fora dos testes.

**Verificações:**
- `php artisan test --compact`: **215 testes aprovados, 823 assertions**.
- `php vendor/bin/phpunit --configuration phpunit.mysql.xml`: **102 testes aprovados,
  552 assertions**, com precisão máxima e overflow de saldo.
- Pint aprovado em **61 arquivos**.
- `php artisan schedule:list`: comando de vencidos reconhecido às 00:05.
- `php artisan migrate:status`: 15 migrations aplicadas.
- Cobertura nova: saldo por data/conta, abertura negativa, histórico, vínculos cruzados,
  cadastros, códigos/soft deletes, edição com/sem baixas, rollback, vencidos, seeders
  reexecutáveis e preservação da sessão.

**Pendências / próximos passos:**
1. Validar disputas entre processos MySQL independentes para numeração, baixa, edição e cancelamento; testes desta etapa são sequenciais/transacionais.
2. Definir estorno auditável preservando a baixa original e tratando recibos/ciclos recorrentes já gerados. Cancelamento com baixas continua bloqueado.
3. Implementar PDF e notificações com deduplicação. `generate_receipt=true` continua sendo rejeitado.
4. Alinhar stack e implementar autenticação, onboarding, layout e telas; importação/exportação depois.
5. Configurar fuso, scheduler e Redis antes da implantação. Não houve alteração de runtime, dependências ou credenciais nesta etapa.

---

### [2026-10-16] — Assistente de IA — Estorno Auditável e Concorrência Real

**Data:** 2026-10-16
**Autor:** Assistente de IA
**O que foi feito:** Retomada pelo `STATUS_DESENVOLVIMENTO.md`; implementado estorno
integral por baixa e testes com processos/conexões PHP independentes no MySQL.

`SettlementReversalService::reverse()` exige autenticado, Gate, UUID, data e motivo.
Registra auditoria separada sem apagar/alterar valores da baixa ou suas formas.
Mesmo pedido/chave retorna o registro existente; outra chave para a mesma baixa ou
conteúdo diferente é rejeitado. Reabre principal, recalcula estados e invalida recibos
existentes com `voided_at`, preservando arquivo e número. Valores/vínculos históricos
inconsistentes exigem reconciliação.

Novos ciclos registram `generated_by_settlement_id` da baixa de fechamento.
Estorno cancela os ciclos rastreáveis sem histórico, sem excluir registros ou reutilizar
números. Ciclo com movimentos, inclusive movimentos estornados, bloqueia o estorno da
origem. Recorrência legada paga sem origem rastreável não recebe backfill por suposição.
Não há estorno parcial de uma baixa, estorno em cascata ou integração bancária nesta entrega.

`TitleService` considera somente baixas ativas no principal, permite cancelamento após
estorno de todas as baixas e impede nova baixa anterior ao último estorno da parcela.
Edição financeira continua bloqueada com histórico, mesmo estornado.
Nova liquidação recorrente cria novo ciclo, mantendo os cancelados no histórico.
`FinancialAccountService` respeita a data do estorno, preservando cortes anteriores.
O relacionamento das parcelas agora ordena explicitamente por número, evitando diferenças
entre planos de consulta SQLite/MySQL após atualização de estados.

Concorrência: `MySqlConcurrencyTest` confirma fixtures exclusivas por commit, inicia
workers independentes e bloqueia o usuário na transação de partida. Confirma que os dois
workers estão em `LOCK WAIT` no InnoDB antes de liberar o lock. Sete cenários cobrem
numeração, mesma baixa/UUID, excesso de principal, edição/baixa, cancelamento/baixa,
mesmo estorno/UUID e origem recorrente versus pagamento do próximo ciclo.
Resultado pode variar conforme o vencedor, mas as invariantes são verificadas.

Workers confirmam banco físico `gestao_financeira_testing` e ID/email exclusivo da fixture.
Conexão/credenciais seguem por ambiente, não argumentos, arquivos ou logs.
Cleanup termina workers ativos e remove somente registros da própria fixture, em ordem
de FKs; não há reset/truncate/limpeza global. Encerramento abrupto do processo principal
pode deixar uma fixture e exige revisão direcionada. É necessário acesso a `innodb_trx`.

**Arquivos de código criados:**
- `database/migrations/2026_10_16_000001_add_auditable_settlement_reversals.php`
- `app/Models/SettlementReversal.php`
- `app/Policies/TitleSettlementPolicy.php`
- `app/Services/SettlementReversalService.php`
- `tests/Concerns/SettlementReversalScenarios.php`
- `tests/Feature/SettlementReversalTest.php`
- `tests/MySql/MySqlSettlementReversalTest.php`
- `tests/MySql/MySqlConcurrencyTest.php`
- `tests/MySql/FinancialConcurrencyWorker.php`

**Arquivos de código alterados:** `app/Models/Title.php`, `TitleSettlement.php`,
`Receipt.php`, `app/Providers/AppServiceProvider.php`, `app/Services/TitleService.php`,
`FinancialAccountService.php`, `tests/Feature/MigrationLifecycleTest.php` e
`tests/MySql/MySqlFinancialTest.php`.

**Documentos atualizados:** `docs/STATUS_DESENVOLVIMENTO.md`, `docs/README.md`,
`docs/DATABASE.md`, `docs/DEVELOPER_GUIDE.md` e `docs/AI_DEV_LOG.md`.
Contratos, diagrama, limites e próximo bloco atualizados; histórico preservado e regras relidas.

**Impacto no banco:** Migration incremental aplicada nos dois bancos locais:
`settlement_reversals` com uma linha por baixa e UUID único por proprietário,
`titles.generated_by_settlement_id` anulável/único com FK restritiva e
`receipts.voided_at` anulável. Sem backfill ou alteração de valores históricos.
Agora **16 migrations, 20 tabelas e 22 FKs restritivas**.
A migration preserva `CHECK` dos enums no SQLite ao adicionar a referência, sem
reconstruir `titles`. Lifecycle, enums, reversão e reaplicação validados em memória.
Nenhum reset/rollback de migrations foi executado no MySQL.

**Verificações:**
- `php artisan test --compact`: **243 testes aprovados, 919 assertions**.
- `php vendor/bin/phpunit --configuration phpunit.mysql.xml`: **137 testes aprovados,
  790 assertions na execução final**. Assertions das disputas podem variar com o vencedor.
- Sete testes de concorrência independentes, todos com contenção verificada no InnoDB.
- Pint aprovado em **70 arquivos PHP**.
- `migrate:status` e `information_schema`: migrations, 20 tabelas e 22 FKs confirmadas.
- Consultas finais: zero usuários, títulos, baixas e estornos nos dois bancos;
  nenhuma fixture de concorrência remanescente.

**Pendências / próximos passos:**
1. Implementar recibos PDF privados, emissão idempotente e numeração protegida; rejeitar emissão/download de baixa estornada ou recibo invalidado.
2. Implementar notificações com deduplicação e reexecução segura.
3. Alinhar stack e desenvolver autenticação, onboarding, layouts e telas; importação/exportação depois.
4. Ampliar carga/concorrência prolongada e oferecer reconciliação explícita de recorrências legadas/cadeias com histórico.
5. Configurar fuso, scheduler e Redis antes da implantação. Runtime, dependências, credenciais e dados financeiros da aplicação não foram alterados.

---

### [2026-10-16] — Assistente de IA — PDF Privado e Notificações Deduplicadas

**Data:** 2026-10-16
**Autor:** Assistente de IA
**O que foi feito:** Retomada pelo status; implementados recibos PDF privados e alertas
internos, com rotas autenticadas, snapshot/hash e concorrência real.

Instalado `barryvdh/laravel-dompdf` 3.1.2 com `dompdf/dompdf` 3.1.6 como dependência
de produção. Renderer desativa PHP, JavaScript e recursos remotos. Template usa campos
escapados, valores monetários em centavos e snapshot dos dados da emissão.
Não é documento fiscal e não há serviço externo de emissão.

`ReceiptService::generate()` emite depois do commit da baixa, recusando transação externa.
Essa separação permite tratar falhas de armazenamento sem desfazer/confundir confirmação
financeira. `generate_receipt=true` na baixa segue rejeitado, com mensagem direcionando
ao Service; não houve alteração dos hashes de pedidos antigos.
Mesma baixa retorna o mesmo recibo/arquivo, com sequência global bloqueada e unicidade no banco.

PDF no disco `receipts`, raiz `storage/app/receipts-private`, fora das raízes servidas
pelos discos local/public, sem URL pública ou link de storage. Caminho por dono/UUID.
Hash SHA-256 e snapshot são guardados no registro. Download recarrega o proprietário,
verifica estorno/invalidação/caminho/hash e lê os bytes sob lock, com headers privados
sem cache. Legados sem snapshot/hash e arquivos corrompidos são rejeitados; ausentes
retornam 404. Não há substituição/regeneração silenciosa. Estorno preserva o arquivo.

Falha normal tenta remover somente o arquivo novo e reverte contador/registro.
Cleanup com erro é reportado sem ocultar o erro original. Banco/filesystem não oferecem
commit atômico; interrupção abrupta pode deixar órfão e continua exigindo recuperação explícita.
O retorno do recibo é recarregado para normalizar a representação JSON do MySQL, que
reordena chaves de objetos sem mudar os dados.

`NotificationService::generateFor()` usa proprietário explícito, lock e transação sem
alterar sessão. Considera parcelas pendentes/vencidas e janela do usuário (0–365 dias).
Deduplica por evento/parcela/vencimento, não por dia: proximidade e atraso são alertas
distintos, e reagendamento permite novo alerta. Alertas antigos permanecem como histórico.
`markRead()` mantém a primeira leitura; `markAllRead()` limita-se ao dono autenticado.
Comando às 08:00 gera somente registros internos, sem e-mail/push/SMS.

Rotas novas: emissão `POST /baixas/{settlement}/recibo`, download
`GET /recibos/{receipt}/download` e leitura `PATCH /notificacoes/{notification}/ler`.
Todas usam `auth`; acesso cruzado retorna 404, hóspedes JSON 401.
Como o login não existe ainda, hóspedes HTML vão temporariamente para `/`.
Tela de notificações/emissão e autenticação seguem para a próxima etapa.

**Arquivos de código criados:**
- `app/Services/ReceiptService.php`
- `app/Services/NotificationService.php`
- `app/Http/Controllers/ReceiptController.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Console/Commands/SendDueSoonNotifications.php`
- `resources/views/receipt.blade.php`
- `database/migrations/2026_10_16_000002_add_receipt_snapshots_and_notification_deduplication.php`
- `tests/Concerns/ReceiptNotificationScenarios.php`
- `tests/Feature/ReceiptNotificationTest.php`
- `tests/MySql/MySqlReceiptNotificationTest.php`

**Arquivos de código alterados:** `composer.json`, `composer.lock`,
`config/filesystems.php`, `bootstrap/app.php`, `routes/web.php`, `routes/console.php`,
`app/Models/Receipt.php`, `app/Services/TitleService.php`,
`tests/Feature/MigrationLifecycleTest.php`, `tests/MySql/MySqlFinancialTest.php`,
`tests/MySql/MySqlConcurrencyTest.php` e `tests/MySql/FinancialConcurrencyWorker.php`.

**Documentos atualizados:** `docs/STATUS_DESENVOLVIMENTO.md`, `docs/README.md`,
`docs/DATABASE.md`, `docs/DEVELOPER_GUIDE.md` e `docs/AI_DEV_LOG.md`.
Exemplos antigos de PDF e alertas substituídos pelos contratos reais; histórico preservado.
Regras de documentação relidas ao finalizar.

**Impacto no banco:** Migration incremental aplicada aos dois bancos locais.
Criada `document_sequences`, inicializada com maior sufixo legado reconhecível ou 0.
Adicionados snapshot/hash anuláveis de recibo e chave anulável única de deduplicação
por proprietário, sem alterar os registros históricos ou valores financeiros.
Agora **17 migrations, 21 tabelas e 22 FKs restritivas**.
Não houve reset de MySQL, seeds na aplicação ou alteração de dados financeiros.

**Verificações:**
- SQLite: **261 testes aprovados, 1000 assertions**.
- MySQL: **158 testes aprovados, 1003 assertions na execução final**.
- Dez disputas independentes, incluindo emissão idempotente, emissão/estorno e alertas idempotentes.
- PDF real verificado por assinatura/hash; snapshot, escaping, privacidade, rotas, integridade,
  falhas de escrita, rollback, leitura e limites de preferências cobertos.
- Pint aprovado em **83 arquivos PHP**.
- Rotas e agendas confirmadas: vencidos às 00:05; alertas às 08:00, fuso atual UTC.
- Schema conferido: 21 tabelas / 22 FKs nos dois bancos.
- Consulta final: zero usuários, títulos, recibos e alertas nos dois bancos; contador da aplicação 0.

Testes MySQL de PDF usam fixtures confirmadas sem transação externa e cleanup limitado
a `artifacts-...@example.test`. Workers continuam limitados a `concurrency-...@example.test`.
PDFs de teste ficam em diretórios temporários exclusivos, removidos ao finalizar.
O contador de recibos do banco de testes pode avançar e não é restaurado.
Lifecycle/migrations por teste continuam restritos ao SQLite em memória.

**Pendências / próximos passos:**
1. Alinhar Livewire 3, MaryUI em produção, Tailwind 3 e PHP 8.3+; runtime atual permanece PHP 8.2.
2. Implementar autenticação, recuperação de senha, onboarding e redirecionamento de hóspedes.
3. Criar layout, notificações visuais e telas de cadastros/títulos/baixa/estorno/emissão.
4. Dashboard, importação/exportação e notificações externas depois.
5. Recuperação explícita de PDFs legados/ausentes/corrompidos/órfãos e reconciliação de cadeias permanecem pendentes.
6. Configurar fuso, scheduler e Redis para implantação; não foram iniciados serviços persistentes.

---

## 2026-10-16 — Autenticação, onboarding e primeira interface navegável

**Data:** 2026-10-16

**Autor:** Agente de IA

**O que foi feito:** Continuidade a partir de `STATUS_DESENVOLVIMENTO.md`, preservando
Services e histórico financeiro. Implementados apresentação, login/logout, registro
e recuperação de senha usando Auth/Password Broker nativos. Registro e inicialização
de 12 categorias/7 formas padrão são atômicos, sem contas ou títulos fictícios.
Normalização de e-mail, senha mínima com letras/números, limitação de falhas por
e-mail/IP, throttling de registro/recuperação e regeneração/invalidação de sessão.
Hóspedes HTML agora vão a `/entrar`; JSON continua recebendo 401.

Instalado Livewire 3.8.10 e promovida MaryUI 2.9 a dependência de produção.
Mantido Tailwind 4 com daisyUI 5, conforme o instalador atual da MaryUI, substituindo
o alvo antigo Tailwind 3. Vite 6.4.4 e plugin Laravel Vite 1 são compatíveis com
Node 20.13.1 disponível. Não foram executados instaladores Breeze/MaryUI que
sobrescreveriam arquivos existentes. PHP permanece em 8.2.0.
A chave local estava ausente e foi gerada por Artisan; nenhuma chave existente
foi rotacionada e nenhum segredo foi documentado.

Layout com navegação lateral, menu recolhível, tema alternável na página e estados
vazios. Painel com contagens de abertos/alertas, próximas seis parcelas e saldo
realizado de até seis contas. Cadastros Livewire de categorias, contas, contatos
e formas permitem criação, edição, busca, paginação e desativação via `CatalogService`.
Receitas/despesas permitem criar títulos simples/parcelados via `TitleService`,
buscar/filtrar/paginar e consultar parcelas/histórico. Módulo/tipo/ID de edição
são propriedades bloqueadas; IDs de ação são recarregados com `OwnerScope`.
Recibos existentes podem ser consultados/baixados e notificações lidas individualmente
ou em lote. `Display` formata BRL por centavos inteiros, sem float.

**Arquivos de código alterados:**
- `composer.json`, `composer.lock`, `package.json`, `package-lock.json` e `.env` local.
- `app/Http/Controllers/AuthController.php` e `WorkspaceController.php`.
- `app/Livewire/Catalog.php`, `Titles.php` e `app/Support/Display.php`.
- `bootstrap/app.php`, `routes/web.php` e `resources/css/app.css`.
- `resources/views/home.blade.php`, `dashboard.blade.php`, `catalog.blade.php`,
  `titles.blade.php`, `title.blade.php`, `receipts.blade.php` e `notifications.blade.php`.
- Views de `resources/views/auth/`, `resources/views/components/` e `resources/views/livewire/`.
- `tests/Feature/WorkspaceTest.php` e expectativa de redirecionamento em
  `tests/Concerns/ReceiptNotificationScenarios.php`.
- Assets compilados em `public/build`.

**Documentos atualizados:** `docs/STATUS_DESENVOLVIMENTO.md`, `docs/README.md`,
`docs/DATABASE.md`, `docs/DEVELOPER_GUIDE.md` e `docs/AI_DEV_LOG.md`.
Histórico anterior preservado. Configuração antiga de Tailwind/Breeze e pendências
de login/telas corrigidas nos documentos ativos.

**Impacto no banco:** Nenhuma migration nova. Permanecem 17 migrations, 21 tabelas
e 22 FKs restritivas. Não foram inseridos dados financeiros, usuários demonstrativos
ou seeds no MySQL da aplicação. Testes HTTP/Livewire usam SQLite em memória;
navegador usou arquivo SQLite temporário fora do projeto. Regressão MySQL mantém
rollback e cleanup limitado aos proprietários exclusivos das fixtures.

**Verificações:**
- SQLite: **280 testes / 1131 assertions**, incluindo 19 novos casos de interface/auth.
- MySQL: **158 testes / 1003 assertions** na repetição completa.
- A primeira execução MySQL falhou na espera por dois `LOCK WAIT` na numeração;
  execução isolada e repetição completa passaram. Não foi reduzida a exigência
  de concorrência; estabilidade sob carga permanece sob observação.
- Pint e `npm run build` aprovados; Composer/npm sem advisories na instalação.
- Navegador desktop: registro/onboarding, conta com saldo, receita de R$ 100,00 em
  três parcelas exatas, detalhes, painel, tema e logout/redirecionamento conferidos.
- A criação do título não alterou o saldo da conta.

**Pendências / próximos passos:**
1. Integrar baixa parcial/total e múltiplas formas com UUID estável por pedido.
2. Integrar estorno, edição/cancelamento e recorrência; emissão de recibo separada após commit.
3. Completar contatos, arquivamento/restauração e persistência de preferências.
4. Dashboard por período/gráficos, importação/exportação e alertas externos.
5. SMTP real (mailer local `log`), PHP 8.3+, Redis, scheduler e fuso.
6. Validação dedicada em viewport mobile, acessibilidade, carga e equivalente MySQL dos testes HTTP/Livewire novos.
7. Reconciliação de legados e recuperação explícita de PDFs continuam pendentes.

---

## 2026-10-16 — Operações financeiras na tela de títulos

**Data:** 2026-10-16

**Autor:** Agente de IA

**O que foi feito:** Continuidade pelo status anterior, integrando o domínio financeiro
existente à interface sem modificar seus Services. Adicionado `TitleDetail` em
`/titulos/{title}`, com principal original/restante por parcela, histórico de
formas/contas e ajustes, estornos auditáveis, recibos e ciclos gerados.

Baixa parcial/total com até 20 formas, desconto, juros, multa e confirmação explícita.
UUID e IDs são bloqueados; payload confirmado também é bloqueado e mantido para
reenvio. Formulário aberto não recebe nova chave silenciosamente. Novo pedido exige
concluir/fechar explicitamente o anterior. Replay de snapshot antigo com mesmo pedido
não duplica baixa; conteúdo alterado com mesma chave é rejeitado pelo hash persistido.

Estorno integral da baixa escolhida, com motivo/data, UUID e reenvio idempotente.
Invalida recibos e preserva baixa/valores/formas; não faz estorno parcial ou cascata.
Cancelamento exige ausência de baixas ativas. Edição financeira antes de histórico
mantém IDs de parcelas e vínculos não alterados/inativos; após baixa, inclusive
estornada, expõe somente metadados. Baseline de edição bloqueado e envio apenas de
diferenças. O Service continua revalidando concorrência entre edição e baixa.

Emissão de PDF em ação independente após commit da baixa, sem transação externa.
Recibo existente é verificado em vez de substituído; corrupção/ausência do arquivo
e falha de armazenamento aparecem como erros na tela sem desfazer a movimentação.
Recorrência mensal/anual adicionada à criação de títulos, validando uma parcela,
dia/mês e vencimento. Próximo ciclo gerado pelo Service na baixa total e mostrado
no detalhe. Edição da configuração de recorrência ainda não está exposta.

**Arquivos de código alterados:**
- `app/Livewire/TitleDetail.php` (novo) e `app/Livewire/Titles.php`.
- `resources/views/livewire/title-detail.blade.php` (novo),
  `resources/views/livewire/titles.blade.php` e `resources/views/title.blade.php`.
- `app/Http/Controllers/WorkspaceController.php`, removendo carga duplicada do detalhe.
- `tests/Feature/TitleDetailTest.php` (novo).
- Assets compilados em `public/build`.

**Documentos atualizados:** `docs/STATUS_DESENVOLVIMENTO.md`, `docs/README.md`,
`docs/DATABASE.md`, `docs/DEVELOPER_GUIDE.md` e `docs/AI_DEV_LOG.md`.
Histórico anterior preservado; pendências de integração financeira corrigidas.

**Impacto no banco:** Nenhuma migration, nova tabela, dependência ou configuração
de banco alterada. Permanecem 17 migrations, 21 tabelas e 22 FKs restritivas.
Services e índices de idempotência existentes preservados. Nenhum reset ou seed
no MySQL da aplicação. Navegador usou SQLite/PDFs temporários fora do projeto.
PDF nos testes exige transação própria; `DatabaseMigrations` limitado a SQLite
em memória. Teardown desvincula autorreferências de fixtures somente após confirmar
esse banco descartável, para permitir rollback do schema. Nenhuma limpeza global MySQL.

**Verificações:**
- SQLite: **303 testes / 1297 assertions**, incluindo 23 novos testes.
- MySQL: **158 testes / 1003 assertions**, com dez disputas reais aprovadas.
- Replay HTTP de snapshot antigo, colisão UUID/payload e bloqueio de propriedades.
- Principal restante, formas/ajustes, edição versus baixa e preservação de IDs.
- PDF real e download autorizado, corrupção/ausência/armazenamento sem desfazer baixa.
- Recorrência mensal com ajuste de fim de mês e cancelamento de filho sem histórico no estorno; criação anual validada.
- Navegador desktop: baixa de R$ 40,00 sobre R$ 100,00, restante R$ 60,00,
  replay sem duplicação, PDF emitido separadamente, estorno/invalidação,
  edição somente de metadados após estorno e cancelamento com histórico preservado.
- Build, Pint e compilação Blade verificados ao concluir.

**Pendências / próximos passos:**
1. Contatos completos, arquivamento/restauração e preferências persistentes.
2. Indicadores/gráficos por período e principal restante no painel.
3. Resumo de total/formas, edição de recorrência, rascunhos e reconciliação de pedidos após reload.
4. Equivalentes MySQL dos testes HTTP/Livewire, viewport mobile e acessibilidade.
5. Importação/exportação e alertas externos após consolidar os módulos.
6. SMTP, PHP 8.3+, Redis, scheduler e fuso; recuperação explícita de PDFs e legados.

**Limitações:** Pedidos/UUIDs em aberto não persistem após recarregar a página.
Após falha de conexão, conferir o histórico antes de criar outro UUID. Chaves
diferentes representam operações distintas. Baixa mantém contrato de data futura
existente; emissão de PDF aguarda a data e estorno não aceita data futura.

---

## 2026-10-16 — Contatos, preferências e indicadores iniciais

**Data:** 2026-10-16

**Autor:** Agente de IA

**O que foi feito:** A tela de contatos passou a editar endereço, bairro, cidade,
UF, CEP e observações, além de arquivar/restaurar contatos com histórico preservado.
Foi adicionada a rota `/preferencias` com tema claro/escuro e antecedência de alertas
(0 a 365 dias) persistidos. O painel agora exibe totais mensais abertos/realizados, desconta estornos
ocorridos no período e mostra o principal restante nos próximos compromissos.

**Arquivos de código alterados:**
- `app/Services/UserPreferencesService.php` (novo).
- `app/Livewire/UserPreferences.php` (novo), `app/Livewire/Catalog.php` e `app/Http/Controllers/WorkspaceController.php`.
- `resources/views/livewire/user-preferences.blade.php` e `resources/views/preferences.blade.php` (novos).
- `resources/views/livewire/catalog.blade.php`, `resources/views/components/layout.blade.php`,
  `resources/views/dashboard.blade.php` e `routes/web.php`.
- `tests/Feature/WorkspaceTest.php` e assets compilados em `public/build`.

**Documentos atualizados:** `docs/STATUS_DESENVOLVIMENTO.md`, `docs/README.md`,
`docs/DATABASE.md`, `docs/DEVELOPER_GUIDE.md` e este log.

**Impacto no banco:** Nenhuma migration, tabela, dependência ou configuração alterada.
Foram usados os campos existentes de `contacts` e `users`; nenhuma operação de reset,
seed ou escrita no MySQL da aplicação foi executada.

**Verificações:**
- SQLite: **306 testes / 1320 assertions**.
- Testes de interface cobrem detalhes completos/arquivamento/restauração de contato,
  preferências persistidas e painel com baixa parcial.
- `php artisan view:cache`, Pint e `npm run build` aprovados.
- MySQL executou 157/158 testes na passagem completa: a asserção estrita que observa
  dois `LOCK WAIT` falhou por agendamento de processos. O cenário isolado passou
  em seguida (1 teste / 11 assertions), sem reduzir a proteção de concorrência.

**Pendências / próximos passos:**
1. Resumo interativo de total/formas, edição de recorrência, rascunhos e reconciliação após reload.
2. Gráficos e filtros de período no painel.
3. Viewport mobile, acessibilidade e cobertura HTTP/Livewire correspondente no MySQL.
4. Importação/exportação, alertas externos, SMTP, PHP 8.3+, Redis, scheduler/fuso e recuperação de legados/PDFs.

---

## 2026-10-16 — Operação de baixa e atualização imediata de alertas

**Data:** 2026-10-16

**Autor:** Agente de IA

**O que foi feito:** Reproduzidos os fluxos de baixa e alertas internos. A baixa já
funcionava pelo `TitleService`; o impedimento prático era a ausência de conta financeira
ativa ou forma compatível para usuários novos. `TitleDetail::openPayment()` agora bloqueia
o pedido antes de gerar UUID e mostra uma mensagem acionável. A geração de alertas também
funcionava, mas dependia do scheduler diário que não está iniciado automaticamente no
ambiente local. Foi adicionada atualização manual em `/notificacoes`, sempre limitada ao
proprietário autenticado e idempotente por parcela/evento/vencimento.

**Arquivos de código alterados:**
- `app/Livewire/TitleDetail.php`.
- `app/Http/Controllers/WorkspaceController.php` e `routes/web.php`.
- `resources/views/notifications.blade.php`.
- `tests/Feature/TitleDetailTest.php` e `tests/Feature/WorkspaceTest.php`.
- Assets compilados em `public/build`.

**Documentos atualizados:** `docs/STATUS_DESENVOLVIMENTO.md`, `docs/README.md`,
`docs/DATABASE.md`, `docs/DEVELOPER_GUIDE.md` e este log.

**Impacto no banco:** Nenhuma migration, tabela, dependência ou configuração de banco
alterada. Nenhum reset, seed ou escrita no MySQL da aplicação foi executado.

**Verificações:**
- SQLite: **308 testes / 1338 assertions**.
- Baixa total/parcial segue aprovada; novo teste cobre conta/forma inativa antes da abertura.
- Atualização manual cria alertas de parcela próxima e vencida, sem duplicar na repetição.
- Rotas, cache de views, Pint e build de produção aprovados.
- MySQL focal de títulos e notificações: **70 testes / 389 assertions** aprovados.

**Pendências / próximos passos:**
1. Resumo interativo de total/formas, edição de recorrência, rascunhos e reconciliação após reload.
2. Gráficos/filtros do painel, viewport mobile, acessibilidade e testes HTTP/Livewire MySQL.
3. Importação/exportação, alertas externos, SMTP, scheduler/fuso, Redis, PHP 8.3+ e recuperação de legados/PDFs.

---

## 2026-10-22 — Conferência de baixa e edição segura de recorrência

**Data:** 2026-10-22

**Autor:** Agente de IA

**O que foi feito:** A tela de detalhe passou a exibir uma prévia reativa do total da
baixa, da soma das formas e da diferença entre ambos, calculada somente em centavos.
O `TitleService` continua sendo a validação final da operação. A edição de título sem
histórico agora permite ativar, alterar ou desativar recorrência mensal/anual; campos
incompatíveis são normalizados pela interface e as proteções do Service para baixas e
ciclos já gerados permanecem ativas.

**Arquivos de código alterados:**
- `app/Livewire/TitleDetail.php`.
- `resources/views/livewire/title-detail.blade.php`.
- `tests/Feature/TitleDetailTest.php`.
- Assets compilados em `public/build`.

**Documentos atualizados:** `docs/STATUS_DESENVOLVIMENTO.md`, `docs/README.md`,
`docs/DATABASE.md`, `docs/DEVELOPER_GUIDE.md` e este log.

**Impacto no banco:** Nenhuma migration, tabela, dependência ou configuração alterada.
Nenhum reset, seed ou escrita no MySQL da aplicação foi executado.

**Verificações:**
- SQLite: **311 testes / 1362 assertions**.
- `TitleDetailTest`: **27 testes / 197 assertions**.
- MySQL focal do domínio de títulos: **52 testes / 215 assertions**.
- Cache de views, Pint e build de produção aprovados.

**Pendências / próximos passos:**
1. Persistência de rascunhos e reconciliação de pedidos após reload.
2. Gráficos/filtros do painel, viewport mobile, acessibilidade e testes HTTP/Livewire MySQL.
3. Importação/exportação, alertas externos, SMTP, scheduler/fuso, Redis, PHP 8.3+ e recuperação de legados/PDFs.

---

## 2026-10-22 — Dashboard financeira e relatório imprimível refinado

**Data:** 2026-10-22

**Autor:** Agente de IA

**O que foi feito:** Padronizados os campos de inclusão e edição de títulos, categorias
e contas. Criada a dashboard financeira analítica com filtros compartilhados, KPIs,
evolução diária, distribuição por categoria/forma e seleção múltipla de contatos. O
relatório por agrupamento foi refinado para impressão/salvamento em PDF pelo navegador:
cabeçalho de período, cards de indicadores, contexto dos filtros, agrupamentos sem a
palavra “Nível”, subtotais destacados, total geral, paleta financeira e linhas zebra.

**Arquivos de código alterados:**
- `app/Services/FinancialAnalyticsService.php` e `app/Livewire/FinancialDashboard.php`.
- `routes/web.php` e `resources/views/financial-dashboard.blade.php`.
- `resources/views/livewire/financial-dashboard.blade.php`.
- `resources/views/components/financial-report-group.blade.php`.
- `resources/views/livewire/titles.blade.php`, `app/Livewire/Titles.php`,
  `resources/views/livewire/catalog.blade.php` e `app/Livewire/Catalog.php`.
- `resources/css/app.css`, testes de dashboard/MySQL e assets compilados em `public/build`.

**Documentos atualizados:** `docs/STATUS_DESENVOLVIMENTO.md`, `docs/README.md`,
`docs/DATABASE.md`, `docs/DEVELOPER_GUIDE.md` e este log.

**Impacto no banco:** Nenhuma migration, tabela, dependência ou configuração foi alterada.
A análise apenas lê baixas, estornos e formas existentes. O relatório não gera PDF
persistido ou registros financeiros. Nenhum reset, seed ou escrita no MySQL da aplicação
foi executado.

**Verificações:**
- SQLite completo: **321 testes / 1426 assertions**.
- `FinancialDashboardTest`: **10 testes / 64 assertions**.
- `MySqlFinancialAnalyticsTest` aprovado no banco exclusivo de testes.
- Cache de views, Pint e build de produção aprovados.
- Dashboard conferida no navegador com SQLite temporário fora do projeto.

**Pendências / próximos passos:**
1. Persistir rascunhos e reconciliar pedidos financeiros após recarregar a página.
2. Validar responsividade, acessibilidade e impressão de relatório longo.
3. Ampliar testes HTTP/Livewire MySQL, importação/exportação, alertas externos e configuração de produção.

---

## 2026-10-22 — Correção de impressão branca e identificação do relatório

**Data:** 2026-10-22

**Autor:** Agente de IA

**O que foi feito:** Corrigido o CSS de impressão para impedir que o tema escuro do
sistema deixe o fundo da página preto. O cabeçalho próprio do relatório deixou de ser
ocultado pela regra global de navegação. Cards e parâmetros têm grade fixa e espaçamento
adequado em PDF; labels foram reforçados. O rodapé agora identifica MoneyControl,
usuário/e-mail que solicitou o relatório e data/hora de emissão.

**Arquivos de código alterados:**
- `resources/views/livewire/financial-dashboard.blade.php`.
- `resources/css/app.css`.
- `tests/Feature/FinancialDashboardTest.php`.
- Assets compilados em `public/build`.

**Documentos atualizados:** `docs/STATUS_DESENVOLVIMENTO.md`, `docs/README.md`,
`docs/DEVELOPER_GUIDE.md` e este log.

**Impacto no banco:** Nenhum. A impressão continua uma renderização local do navegador,
sem criar PDF persistido, movimentações ou registros.

**Verificações:**
- `FinancialDashboardTest`: **10 testes / 73 assertions**.
- Regressão SQLite completa: **321 testes / 1435 assertions**.
- Cache de views, Pint e build de produção aprovados.

**Pendências / próximos passos:**
1. Validar impressão de relatório longo e responsividade em viewport menor.
2. Persistir rascunhos e reconciliar pedidos financeiros após recarregar a página.
3. Ampliar testes HTTP/Livewire MySQL, importação/exportação, alertas externos e configuração de produção.

---

## 2026-10-22 — Impressão visível, tabela de movimentos e gráfico interativo

**Data:** 2026-10-22

**Autor:** Agente de IA

**O que foi feito:** Removida a regra de impressão que ocultava o ancestral Livewire
do relatório e produzia PDF vazio. Controles usam uma classe explícita de ocultação,
sem afetar o relatório. Grupos grandes podem atravessar páginas. A tabela de valores
por data usa somente datas com eventos, incluindo baixa/estorno que se anulam;
o gráfico continua com todos os dias do filtro e ganhou tooltip por posição, toque
e navegação pelas setas do teclado.

**Arquivos de código alterados:** `resources/css/app.css`,
`resources/views/livewire/financial-dashboard.blade.php`,
`resources/views/components/financial-timeline.blade.php`,
`app/Services/FinancialAnalyticsService.php`,
`tests/Feature/FinancialDashboardTest.php` e assets compilados.

**Documentos atualizados:** `README.md`, `DATABASE.md`, `DEVELOPER_GUIDE.md`,
`STATUS_DESENVOLVIMENTO.md` e este log, todos em `docs`.

**Impacto no banco:** Nenhuma migration ou escrita na aplicação. Nenhuma dependência
adicionada ao projeto. Testes de navegador usaram SQLite e ferramentas temporárias
fora do projeto.

**Verificações:**
- SQLite: **324 testes / 1446 assertions**; focal: **13 testes / 84 assertions**.
- Pint, cache de views e build aprovados.
- Edge headless: tooltip, teclado, fundo branco, cabeçalho, tabela e ancestrais visíveis
  em modo print; PDF temporário gerado com conteúdo.
- MySQL não reexecutado nesta correção; cálculos monetários existentes preservados.

**Pendências / próximos passos:**
1. Ampliar testes de impressão com relatórios extensos e diferentes navegadores.
2. Persistir rascunhos e reconciliar pedidos financeiros após recarregar a página.
3. Ampliar cobertura MySQL da interface e ajustes de implantação.
