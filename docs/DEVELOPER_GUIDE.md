# 📚 Guia do Desenvolvedor — MoneyControl

> Este documento serve como referência completa para qualquer desenvolvedor que trabalhar no projeto.
> Leia-o antes de qualquer implementação. Ele responde ao "por quê" das decisões, não apenas ao "como".

---

## 📑 Índice

0. [Setup e Execução Local](#0-setup-e-execução-local)
1. [Filosofia do Projeto](#1-filosofia-do-projeto)
2. [Arquitetura Geral](#2-arquitetura-geral)
3. [Livewire 3 — Guia Prático](#3-livewire-3--guia-prático)
4. [MaryUI — Componentes Utilizados](#4-maryui--componentes-utilizados)
5. [Tema Dark/Light com Tailwind](#5-tema-darklight-com-tailwind)
6. [Módulo de Títulos — Regras de Negócio](#6-módulo-de-títulos--regras-de-negócio)
7. [Sistema de Baixas](#7-sistema-de-baixas)
8. [Títulos Recorrentes](#8-títulos-recorrentes)
9. [Sistema de Notificações](#9-sistema-de-notificações)
10. [Gerador de Recibos PDF](#10-gerador-de-recibos-pdf)
11. [Importação e Exportação de Planilhas](#11-importação-e-exportação-de-planilhas)
12. [Dashboard — Gráficos, KPIs e Relatório](#12-dashboard--gráficos-kpis-e-relatório)
13. [Segurança e Isolamento de Dados](#13-segurança-e-isolamento-de-dados)
14. [Convenções de Código](#14-convenções-de-código)
15. [Fluxo de Trabalho Git](#15-fluxo-de-trabalho-git)
16. [Atalhos de Arraste, Confirmações e Mensagens](#16-atalhos-de-arraste-confirmações-e-mensagens)
17. [Checklist para Novas Funcionalidades](#17-checklist-para-novas-funcionalidades)

---

## 0. Setup e Execução Local

O fluxo do zero (pré-requisitos, `.env`, migrations, `storage:link`, build de assets e execução) fica centralizado no [README.md](README.md).

### Estado verificado em 16/10/2026

- Laravel 12 inicializado; dependências do lock instaladas com `composer install --no-interaction --no-progress --no-cache`, contornando a falha de permissão do cache anterior.
- As 11 migrations `2026_10_15_000001` a `2026_10_15_000011` adicionam perfil e as dez tabelas financeiras, na ordem definida em `DATABASE.md`.
- A migration incremental `2026_10_15_000012` adiciona UUID/hash de idempotência e índice único às baixas, preservando o histórico.
- A migration `2026_10_16_000001` adiciona estornos auditáveis, origem rastreável de novos ciclos e invalidação de recibos.
- A migration `2026_10_16_000002` adiciona sequência de documentos, snapshot/hash de recibos e chave de deduplicação das notificações.
- A migration `2026_10_23_000001` adiciona `deleted_at` (exclusão lógica) a `categories`, `financial_accounts` e `payment_types`. Ver [AUDITORIA_SOFTDELETE.md](AUDITORIA_SOFTDELETE.md).
- `User` inclui `avatar`, `theme` e `notification_days_before`; novos usuários usam tema `dark` e antecedência de 7 dias, com cast inteiro.
- `.env.example` referencia MoneyControl, MySQL e Redis (`CACHE_STORE`, não `CACHE_DRIVER`). O `.env` local usa MySQL e mantém cache/sessão/filas em `database`.
- O ambiente disponível tem PHP 8.2.0 com `pdo_mysql` e SQLite, além de MySQL 8.0.31 instalado e em execução no Wamp. O banco `gestao_financeira` recebeu as 18 migrations (a de 23/10/2026 adiciona `deleted_at` aos cadastros). Em 23/10/2026 o banco `gestao_financeira_testing` não existia nesta máquina; criá-lo conforme [Testes de Integração MySQL](#testes-de-integração-mysql) antes de rodar `phpunit.mysql.xml`. Redis/`phpredis` continuam pendentes. PHP 8.3+ continua sendo o requisito alvo, embora o skeleton ainda declare `^8.2`.
- Livewire 3.8.10 e MaryUI 2.9 instalados em produção. Tailwind 4 e daisyUI 5 são a combinação atual compatível com MaryUI 2.9, substituindo o alvo anterior Tailwind 3. Vite 6.4.4/plugin Laravel Vite 1 funcionam com Node 20.13.1. Breeze 2.4 permanece somente em desenvolvimento; seu instalador não foi executado para evitar sobrescrever rotas/layouts.
- Onze Models financeiros, relacionamentos, casts, soft deletes e isolamento por proprietário estão implementados, com `FinancialRecordPolicy`, `TitlePolicy` e `TitleSettlementPolicy`.
- `TitleService`, `RecurrenceService` e `Money` implementam títulos/parcelas, baixas parciais/totais, idempotência, cancelamento, edição controlada e recorrência.
- `FinancialAccountService` calcula saldo; `CatalogService` cria/edita cadastros e arquiva/restaura contatos. `UserPreferencesService` persiste tema e antecedência de alertas. `FinancialAnalyticsService` consolida baixas/estornos para a dashboard financeira e relatório.
- `OverdueTitleService` e `titles:update-overdue` atualizam vencidos; agenda às 00:05 com prevenção de sobreposição.
- `FinancialDefaultsService`, `finance:initialize`, `CategorySeeder` e `PaymentTypeSeeder` criam cadastros ausentes. `DatabaseSeeder` não cria usuários de exemplo.
- `SettlementReversalService` estorna integralmente baixas com auditoria, recalcula principal/estados e invalida recibos existentes. Ciclos sem movimentos são cancelados; cadeias com histórico/legadas ambíguas são bloqueadas.
- `ReceiptService` emite PDF privado com snapshot/hash, numeração global e download autorizado, em operação independente da baixa.
- `NotificationService` e `notifications:send-due-soon` geram alertas internos deduplicados e controlam leitura; agenda às 08:00. A interface também permite atualização manual do próprio usuário.
- DomPDF 3.1.2 / renderizador 3.1.6 instalados como dependência de produção. Autenticação/onboarding e primeiras telas implementados; alertas externos e operações financeiras completas na interface seguem pendentes. Runtime não foi alterado.
- `APP_KEY` local foi gerada porque estava ausente; não foi rotacionada uma chave existente.

### Validação desta etapa

```powershell
php artisan test
php vendor/bin/pint --test app/Models app/Policies app/Services app/Support app/Console app/Http/Controllers app/Providers/AppServiceProvider.php bootstrap/app.php config/financial_defaults.php config/filesystems.php database/migrations database/seeders routes tests
php vendor/bin/phpunit --configuration phpunit.mysql.xml
```

Resultado atual: **368 testes aprovados, 1730 assertions em SQLite** (24/10/2026) e
**158 testes aprovados, 1003 assertions na última execução em MySQL** (anterior à migration de 23/10/2026).
Em 24/10/2026 o focal `MySqlFinancialAnalyticsTest` passou com **1 teste / 18 assertions**;
a migration aditiva de SoftDelete já existente foi aplicada somente ao banco de testes.
O banco `gestao_financeira_testing` existe nesta conferência; a suíte completa MySQL
ainda não foi reexecutada após essa migration.

Se houver configuração da aplicação em cache, não a reutilize nos testes. Nesta entrega,
ela fazia o runtime usar o ambiente e o banco da aplicação apesar do XML de testes.
Para isolar sem apagar o cache da aplicação, use um caminho temporário inexistente:

```powershell
$env:APP_CONFIG_CACHE = "$env:TEMP/moneycontrol-testing-config.php"
php vendor/bin/phpunit --configuration phpunit.xml
php vendor/bin/phpunit --configuration phpunit.mysql.xml --filter=MySqlFinancialAnalyticsTest
Remove-Item Env:APP_CONFIG_CACHE
```

As travas dos testes MySQL exigem `gestao_financeira_testing` antes de criar fixtures.
`UserPreferencesTest` cobre defaults, cast e proteção de credenciais;
`FinancialSchemaTest` cobre persistência da cadeia financeira, enums, unicidade, vínculos,
exclusões restritas, manutenção do histórico e rollback transacional;
`MigrationLifecycleTest` aplica, reverte e reaplica as migrations.
`FinancialModelsTest` cobre os dez Models, relacionamentos, casts, binding de IDs,
consultas sem autenticação, acesso cruzado, Policies, mass assignment e histórico.
`TitleServiceScenarios` é compartilhado pelas suites SQLite/MySQL e cobre contratos,
centavos, parcelas, baixas, idempotência, cancelamento, vínculos cruzados e rollback
durante gravação/recorrência. `MoneyTest` e `RecurrenceServiceTest` cobrem valores e calendários.
`FinancialOperationsScenarios` compartilha testes de saldo, cadastros, edição, vencidos,
comandos e seeders entre SQLite/MySQL. Inclui rollback e acesso cruzado.
A suite MySQL também testa saldo no limite de `DECIMAL(15,2)` e rejeição de overflow agregado.
`SettlementReversalScenarios` cobre estornos integrais/parciais do título, idempotência,
datas, saldo histórico, recibos, recorrência, acesso cruzado e rollback.
`ReceiptNotificationScenarios` cobre emissão real de PDF, privacidade, integridade,
rotas, rollback/limpeza de arquivo, notificações e leitura. PDF exige transação própria:
esses testes usam migrations por caso no SQLite e fixtures com commit/cleanup no MySQL.
Arquivos de teste ficam fora do projeto em diretórios temporários exclusivos.
`MySqlConcurrencyTest` exercita dez disputas entre processos PHP independentes, com
espera de lock verificada no InnoDB. O número de assertions pode variar conforme a ordem
dos vencedores, pois ambos os resultados legítimos são verificados.

O `phpunit.xml` força SQLite `:memory:`, chaves estrangeiras ativas e uma chave de aplicação
exclusivamente de teste. Nunca use essa chave fora dos testes. As migrations agora estão
aplicadas no MySQL local; nenhum banco de produção foi acessado. SQLite não comprova precisão `DECIMAL`,
limites de `VARCHAR`, collation, concorrência ou execução dos índices no MySQL.
Precisão decimal, overflow, enums, collation, FKs, índices e consultas dos Models foram
verificados separadamente na suite MySQL. Leituras com bloqueio e idempotência estão
implementadas e verificadas entre processos independentes. Carga prolongada e reconciliação
de cadeias legadas continuam pendentes.

Antes de aplicar no banco definitivo, disponibilize PHP 8.3+ com `pdo_mysql`,
crie o banco MySQL de desenvolvimento seguindo o README e execute `php artisan config:clear`
e `php artisan migrate`. Configure Redis somente quando disponível; o ambiente local pode
manter os drivers `database`. Use MySQL 8.0+, InnoDB, `utf8mb4`,
`utf8mb4_unicode_ci` e modo estrito, conforme `config/database.php`.
Não use `migrate:fresh` ou `migrate:reset` em bancos com dados.

### Interface e Autenticação Implementadas

`AuthController` usa Auth/Password Broker nativos, valida senha com pelo menos oito
caracteres, letras e números e normaliza o e-mail do registro antes de validar unicidade.
Criação de usuário e `FinancialDefaultsService::initialize()` são atômicos. Não recebe
proprietário externo e não cria dados financeiros fictícios. Login limita cinco falhas
por e-mail/IP por minuto; registro, solicitação e redefinição usam throttling adicional.
Login/registro regeneram sessão; logout invalida sessão e renova CSRF.
A confirmação de recuperação é igual para e-mails cadastrados ou ausentes.
SMTP não foi configurado: `MAIL_MAILER=log` local não entrega e-mail externo.
Verificação de e-mail, MFA e gerenciamento de sessões/dispositivos ainda não implementados.

| Rota | Funcionalidade |
|---|---|
| `/` | Apresentação e acesso ao login/registro |
| `/entrar`, `/criar-conta`, `/sair` | Autenticação; logout somente POST |
| `/esqueci-senha`, `/redefinir-senha/{token}` | Solicitação e redefinição com Password Broker |
| `/painel` | Contagens, totais mensais aberto/realizado, próximas seis parcelas restantes e saldo de até seis contas |
| `/dashboard-financeira` | Filtros, KPIs, gráficos e relatório imprimível de movimentos realizados |
| `/cadastros/{module}` | `categorias`, `contatos`, `contas`, `formas`; editar, ativar/desativar, excluir (lógico) e restaurar |
| `/receitas`, `/despesas` | Títulos por tipo, criação simples/parcelada/recorrente, busca e filtro |
| `/titulos/{title}` | Parcelas (valor, data da baixa e restante quando parcial), baixa/estorno, edição/cancelamento e emissão/download de PDF. `?acao=baixa` ou `?acao=estorno` (atalhos da listagem) apenas abrem o formulário correspondente |
| `/recibos`, `/notificacoes` | Consulta de documentos e alertas |
| `/preferencias` | Tema persistente e antecedência de alertas |
| POST `/notificacoes/atualizar` | Gera alertas do proprietário autenticado imediatamente |
| POST `/notificacoes/{notification}/lida` | Leitura na interface, sem alterar a rota JSON anterior |
| POST `/notificacoes/ler-todas` | Leitura em lote somente do proprietário autenticado |

`WorkspaceController` faz leitura dos Models com `OwnerScope` e usa
`FinancialAccountService::balance()` para saldo realizado. Títulos pendentes não
movimentam saldo. Os cards são contagens, não indicadores financeiros completos.
Próximas parcelas exibem o principal original da parcela, não o saldo restante
após baixas parciais. Consulta de saldo bloqueia o usuário por conta; avaliar uma
leitura agregada consistente se crescer o volume, sem remover a proteção atual.

`Catalog` e `Titles` são Livewire 3 com `WithPagination`. Módulo/tipo e ID de edição
usam `#[Locked]`; todos os IDs de ações são recarregados sob escopo de proprietário.
Os componentes delegam criação/edição/desativação a `CatalogService` e criação de
títulos a `TitleService`. Validações dos Services aparecem no resumo de erros da tela.
Valores monetários são strings com ponto decimal, sem milhar; `Display::money()`
formata em BRL usando centavos inteiros. Nenhum cálculo da interface usa float.

O layout está em `resources/views/components/layout.blade.php`, com navegação lateral,
menu recolhível e estados vazios. Alpine é fornecido pelo Livewire, sem segunda
instância manual. As views de `auth/` usam POST/CSRF; módulos usam views Blade e
`resources/views/livewire/`. Não foi executado scaffolding Breeze/MaryUI.

Cadastros de contatos incluem nome, tipo, documento, e-mail, telefone, endereço,
bairro, cidade, UF, CEP e observações. Todos os cadastros podem ser excluídos logicamente
e restaurados pelo `CatalogService` ("Mostrar excluídos"), sem exclusão física. `UserPreferences` usa `UserPreferencesService`
para validar e persistir tema (`dark`/`light`) e antecedência de 0 a 365 dias.
`TitleDetail` já integra baixa, estorno, edição/cancelamento e PDF usando os Services, sem gravação
direta nos Models. Recorrência mensal/anual pode ser configurada na criação.
Inclusão de títulos expõe também documento, referência e observações; categorias e contas
expõem cor e ícone desde a criação, mantendo os campos equivalentes à edição.

`WorkspaceTest` possui 23 testes SQLite: autenticação, throttling, recuperação,
rollback de onboarding, páginas, cadastros, títulos, centavos, acesso cruzado,
leitura e bloqueio de propriedades Livewire. Os testes HTTP/Livewire novos ainda
não possuem suíte equivalente MySQL; a regressão do domínio MySQL foi mantida.
Navegador desktop: registro, onboarding, conta, receita parcelada, detalhe, painel,
tema e logout verificados em SQLite temporário fora do projeto. Viewport mobile
dedicado, testes de acessibilidade completos e carga continuam pendentes.
A primeira execução MySQL teve falha na observação de dois `LOCK WAIT`; execução
isolada e repetição completa passaram, sem afrouxar a asserção de concorrência.

Para executar localmente, `composer install`, `npm install`, `npm run build` e
`php artisan serve`. Gere `APP_KEY` apenas em instalação nova sem chave. Não
substitua chaves de ambientes com dados. Abra `http://127.0.0.1:8000`.

### Operações Financeiras na Interface

`resources/views/title.blade.php` monta `app/Livewire/TitleDetail.php`; a view do
componente é `resources/views/livewire/title-detail.blade.php`. A rota existente
continua protegida por `auth` e Policy. Não foram adicionadas migrations,
dependências ou novas rotas financeiras nesta etapa.

- `record()`, `installment()` e `settlement()` recarregam IDs com `OwnerScope`;
  parcela e baixa devem pertencer ao título da rota, mesmo que sejam do mesmo usuário.
- `openPayment()` exige conta ativa e forma ativa compatível antes de gerar UUID; então preenche principal restante pelas baixas ativas.
  `settle()` delega ao `TitleService`. Aceita até 20 formas, principal parcial/total,
  desconto/juros/multa e observações. Confirmação explícita antecede a gravação.
- `addMethod()` preenche o valor da nova forma com o que falta (`total esperado − formas já
  informadas`, ignorando valores inválidos); vazio quando nada falta ou o total é inválido.
  É só sugestão: o `TitleService` continua exigindo a soma exata.
- `paymentSummary()` é somente visual: calcula em centavos o total esperado
  (`principal - desconto + juros + multa`), a soma das formas e a diferença. Campos
  incompletos exibem orientação, sem permitir que a prévia substitua a validação final
  do `TitleService`.
- UUID, parcela, título e payload confirmado têm `#[Locked]`. Após sucesso, chave
  e payload permanecem para reenvio. Um formulário ainda aberto não pode receber
  nova chave silenciosamente; outro pedido exige concluir ou fechar o atual.
- Erro de validação mantém UUID e permite correção. Se a baixa já foi confirmada,
  snapshot antigo com mesmo payload retorna a baixa original; conteúdo alterado
  com o mesmo UUID é recusado pelo hash do Service.
- `openReversal()`/`reverse()` usam UUID bloqueado e `SettlementReversalService`,
  motivo/data, confirmação e replay. Estorno é integral da baixa escolhida,
  não parcial nem em cascata. Recibos invalidados continuam no histórico.
- `emitReceipt()` chama `ReceiptService` em ação independente, sem transação externa
  ou emissão automática dentro de `settle()`. Reemissão verifica documento existente.
  Validação de integridade, arquivo ausente e erro de armazenamento aparecem na tela,
  sem desfazer baixa nem regenerar silenciosamente um arquivo inválido.
- `openEdit()` guarda baseline bloqueado. Sem histórico, expõe também recorrência:
  pode ativar, alterar ou desativar a configuração mensal/anual. A normalização
  limpa tipo/dia/mês ao desativar e limpa o mês na recorrência mensal. O Service
  continua bloqueando recorrência em cadeia de ciclos já criada.
  Com qualquer histórico de baixa, só
  expõe descrição/documento/referência/notas. Sem histórico, permite valor/datas/vínculos.
  `saveEdit()` envia somente diferenças, preservando vínculos inativos não alterados.
  O Service revalida concorrência: baixa após abertura bloqueia alteração financeira.
- Baixa, estorno, cancelamento e descarte de pedido aberto passam pelo modal de confirmação
  (seção 16): `requestSettle()`, `requestReverse()`, `confirmCancel()`, `requestClosePayment()`
  e `requestCloseReversal()` só abrem o modal; `settle()`, `reverse()` e `cancel()` consomem a
  confirmação no servidor. Reenvio de pedido já confirmado continua idempotente e sem modal.
- `mount(int $titleId, ?string $action)`: `baixa` abre a primeira parcela em aberto;
  `estorno` abre a baixa ativa mais recente. Erros de estado aparecem no resumo, sem gravar nada.
- `cancel()` chama o Service; o botão só aparece sem baixas ativas. Estado final,
  IDs, valores e histórico são protegidos pelo domínio, não só pelos controles visuais.

O detalhe exibe, por parcela: vencimento, **Valor da parcela** e **Data da baixa** (uma data por
baixa ativa; estornadas não contam, "—" sem baixa). O restante (valor − principal das baixas
ativas) aparece sob o status só quando a parcela está parcialmente baixada, e continua
preenchendo o "Principal a baixar" do formulário. Cada baixa do histórico mostra:
`Título/Parcela · Data da baixa · Valor da parcela`, depois
`Baixa #ID / Vencimento / Desconto / Juros / Multa` e uma linha por forma
(`Forma · Conta · Valor pago`), além de motivo/data de estorno e recibos; e ciclos gerados.
O histórico é ordenado pelo número da parcela (crescente) e, na mesma parcela, pela data e
ID da baixa; baixas sem parcela (legado) ficam por último.
O formulário de baixa mostra o campo informativo (somente leitura) **Vencimento da parcela**,
com indicação "Vencida há N dia(s)", "Vence hoje" ou "Vence em N dia(s)" em relação a hoje.
`WorkspaceController` calcula valores do painel em centavos: parcelas abertas no mês
usam principal restante; baixas realizadas entram pela data da baixa e estornos no
mesmo período reduzem o total realizado. Os próximos compromissos mostram o saldo
restante, não o valor original. Valores são strings com ponto decimal; nenhum formulário calcula com float.
`Titles` normaliza recorrência desmarcada para campos nulos; mensal não usa mês.
Com "Título recorrente" marcado, a inclusão desabilita **Parcelas** (fixa 1) e
**Primeiro vencimento**, que passa a ser calculado no servidor por
`RecurrenceService::firstDueDate()` a partir de emissão + dia (+ mês na anual); a tela mostra
a prévia. Valores digitados antes nesses campos são ignorados. Para registrar um ciclo cujo
dia já passou no mês, informa-se uma emissão anterior. O `TitleService` continua validando
uma parcela e dia/mês compatíveis. O próximo ciclo nasce somente na baixa total.
Os blocos recorrente/não recorrente usam `wire:key` próprios: sem eles o morph do Livewire
reaproveitava campos pela posição e o "Dia previsto" passava a gravar na data de emissão.
O formulário de edição recebeu a mesma proteção. A edição não calcula o vencimento:
continua permitindo informá-lo, validado pelo Service.

Pedidos e rascunhos não persistem após recarregar a página. Em falha de conexão,
conferir histórico e reenviar o pedido original antes de iniciar outro; UUID novo
representa outra operação. Idempotência persistida no banco não deduplica pedidos
com UUIDs diferentes. A baixa permite data futura pelo contrato existente; PDF
aguarda a data da baixa e estorno rejeita data futura.

`TitleDetailTest` possui 27 testes SQLite: reenvio de snapshot antigo por HTTP,
hash divergente, múltiplas formas/ajustes, principal restante, PDF real, corrupção,
ausência/armazenamento, estorno/replay, edição concorrente, cancelamento,
recorrência e isolamento por dono/título. Também cobre a prévia de total/formas,
ativação/desativação de recorrência sem histórico e a rejeição dessa alteração depois
de baixa. Usa `DatabaseMigrations` sem transação
externa para PDF e disco temporário exclusivo. Antes do rollback de schema, desfaz
autorreferências de fixtures somente após confirmar SQLite `:memory:`; não toca MySQL.
Navegador desktop conferiu baixa parcial de R$ 40,00 sobre R$ 100,00, reenvio sem
duplicação, PDF, estorno com invalidação, edição de metadados e cancelamento.
Banco/PDFs de navegador ficaram em diretórios temporários fora do projeto.
A validação da interface confirma mensagens antecipadas quando não há conta/forma ativa.
Equivalentes MySQL dos novos testes HTTP/Livewire, mobile e acessibilidade pendentes.

### Testes de Integração MySQL

`phpunit.mysql.xml` executa somente `tests/MySql/` e força a conexão MySQL e o banco
`gestao_financeira_testing`. Host/porta/credenciais vêm do ambiente local. A suite confirma
o nome real do banco, não migra/apaga tabelas e faz rollback nos testes comuns.
Exceção: concorrência e PDF confirmam fixtures por commit e removem somente seus registros.
Prefixos dos proprietários: `concurrency-...@example.test` e `artifacts-...@example.test`.
Sequência global de recibos do banco de testes pode avançar e não é restaurada.
Não aponte esse banco para dados pessoais ou produção.

Na máquina atual, o banco já está preparado. Em outro ambiente, crie-o no cliente MySQL:

```sql
CREATE DATABASE IF NOT EXISTS gestao_financeira_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Prepare a estrutura no PowerShell sem mudar permanentemente o `.env`:

```powershell
php artisan config:clear
$bancoAnterior = $env:DB_DATABASE
$urlAnterior = $env:DB_URL
try {
    $env:DB_DATABASE = 'gestao_financeira_testing'
    $env:DB_URL = ''
    php artisan migrate --no-interaction
} finally {
    $env:DB_DATABASE = $bancoAnterior
    $env:DB_URL = $urlAnterior
}
php vendor/bin/phpunit --configuration phpunit.mysql.xml
```

Os testes não usam `RefreshDatabase` nessa conexão. Não execute a suite de lifecycle
SQLite contra MySQL, nem modifique `phpunit.xml` para apontar ao banco local.
Não execute seeders no banco de testes MySQL: os testes assumem tabelas de domínio vazias.

### Concorrência Real no MySQL

```powershell
php vendor/bin/phpunit --configuration phpunit.mysql.xml --filter=MySqlConcurrencyTest
```

`MySqlConcurrencyTest` cria um proprietário exclusivo `concurrency-<uuid>@example.test`.
Cada worker tem processo/conexão PHP independente, identidade validada e conexão física
obrigatoriamente `gestao_financeira_testing`. Credenciais são transmitidas pelo ambiente
do processo, nunca por argumentos de comando, arquivos temporários ou logs.

Uma transação de partida bloqueia o usuário. O teste espera que ambos os workers
apareçam em `information_schema.innodb_trx` como `LOCK WAIT`, só então libera o lock.
É preciso permissão de leitura desses dados (normalmente privilégio `PROCESS`) e execução
de subprocessos PHP. Sem esse acesso, o teste falha explicitamente; não simula concorrência.
Cobertura: números distintos, mesma baixa/UUID e um próximo ciclo, principal insuficiente,
edição versus baixa, cancelamento versus baixa, mesmo estorno/UUID e estorno da origem
versus pagamento do próximo ciclo, emissão de recibo idempotente, recibo versus estorno
e notificações idempotentes. PDFs dos workers usam disco temporário exclusivo validado.

Cleanup interrompe workers ainda ativos, reverte a barreira e remove somente registros
da fixture identificada por ID/email, respeitando FKs. Não usa reset, truncate ou limpeza
global. Encerramento abrupto do processo principal pode deixar a fixture no banco de testes;
revisar somente o proprietário exclusivo remanescente, sem apagar outros registros.
Não executar `FinancialConcurrencyWorker.php` manualmente ou apontá-lo ao banco da aplicação.

### Troca para MySQL (15/10/2026)

Validação após a troca: **38 testes aprovados, 92 assertions** em SQLite em memória,
incluindo a presença dos índices de busca/dashboard. Pint aprovado nos 16 arquivos PHP
verificados (configuração, migrations e teste de estrutura). `php artisan config:clear` concluído.

- `.env`, `.env.example` e conexão padrão do Laravel alinhados a MySQL, porta 3306 e banco `gestao_financeira`.
- Índice GIN de contatos substituído por `(user_id, name)` e `(user_id, document)`.
- Índice de parcelas agora composto `(title_id, status, due_date)`, sem filtro parcial.
- Notificações não lidas usam o índice existente `(user_id, read_at)`, sem duplicação.
- Exemplos de busca usam `LIKE`, compatível com MySQL. A collation selecionada é insensível
  a maiúsculas/minúsculas e acentos; índices não garantem otimização de `LIKE '%termo%'`.
- Testes continuam forçados em SQLite em memória, sem atingir o banco local.
- Migrations iniciais ajustadas antes da primeira aplicação local. Ambientes que já as
  executaram precisam de migration incremental para atualizar os índices.
- Nenhum banco foi criado, apagado ou teve dados transferidos nesta troca.

### Próxima sequência

1. Refinar formulários financeiros, edição de recorrência e rascunhos/reconciliação após reload.
2. Evoluir os indicadores mensais para gráficos e filtros de período; importação/exportação e alertas externos depois.
3. Validar viewport mobile/acessibilidade e ampliar a cobertura HTTP/Livewire no MySQL.
4. Atualizar PHP para 8.3+ e configurar SMTP, Redis, scheduler e fuso; frontend já alinhado.

Saldo, edição controlada, cadastros, vencidos, seeders, estorno básico e dez cenários
de concorrência real, PDF privado e alertas internos já estão implementados.
Carga, recuperação de PDFs e reconciliação de cadeias ficam pendentes.
Consulte [STATUS_DESENVOLVIMENTO.md](STATUS_DESENVOLVIMENTO.md) antes da próxima retomada.

As chaves estrangeiras verificam existência, não propriedade do registro. `TitleService` já
rejeita vínculos cruzados e valida centavos, principal e parcelas; os demais Services devem
rejeitar vínculos de outro usuário, garantir que parcela e baixa pertençam ao mesmo título,
validar dias/meses de recorrência, valores positivos, soma exata das formas de pagamento e
saldo restante. A numeração implementada usa lock do usuário até o commit e o maior
sufixo existente, incluindo títulos excluídos logicamente; não use `count() + 1`.

A tabela `notifications` segue o esquema customizado de `DATABASE.md`, não o esquema UUID/data
do canal `database` nativo do Laravel. Essa integração deve ser resolvida na etapa de notificações;
não utilizar o canal nativo diretamente com esta tabela. Use `User::financialNotifications()`
para os registros financeiros, preservando o nome de relacionamento nativo de `Notifiable`.

### Models Financeiros Entregues

`Category`, `FinancialAccount`, `Contact`, `PaymentType`, `Title`, `TitleInstallment`,
`TitleSettlement`, `TitleSettlementMethod`, `Receipt` e `Notification` estendem
`FinancialModel`. Nomes e relacionamentos estão em [DATABASE.md](DATABASE.md#relacionamentos).
`Contact`, `Title`, `Category`, `FinancialAccount` e `PaymentType` têm soft delete; relacionamentos
históricos (`Title`, `TitleInstallment`, `TitleSettlementMethod`) usam `withTrashed()` para o
contato, título, categoria, conta ou forma excluídos logicamente, mantendo `OwnerScope`.

Os campos de identidade e relacionamento (`id`, `user_id` e demais FKs) não são mass assignable.
Os Services devem autorizar primeiro, resolver registros do proprietário e usar
`associate()`, `user->titles()->save()` ou os demais relacionamentos para persistir vínculos.
Criar um Model com um array de IDs vindo da interface não é o fluxo suportado.
`save()`, `forceFill()`, relacionamentos `create()` e queries sem escopo não são uma fronteira
de autorização: precisam de validação explícita no Service, inclusive quando o objeto já
estiver carregado. O escopo protege consultas, não valida coerência de todos os vínculos.

```php
// Exemplo de persistência de vínculo após validação, não um Service completo.
$titulo = new Title($dadosValidadosSemIds);
$titulo->category()->associate($categoriaDoUsuario);
$usuario->titles()->save($titulo);
```

`decimal:2` retorna strings; use centavos inteiros ou aritmética decimal exata nos Services.
Os casts não validam soma das formas de pagamento, saldo restante ou fórmula de baixa.
Parcelas e recorrência são calculadas pelo `TitleService`; saldo realizado por conta/data
é calculado pelo `FinancialAccountService`, sem somas com `float`.

### Services Financeiros Entregues

`TitleService::create(array $dados): Title` valida o autenticado, tipos, vínculos ativos,
datas e valores. Aceita `title_type`, `description`, `original_value`, `issue_date`,
`due_date`, `installments_count` (1–120), IDs opcionais de contato/categoria/conta,
recorrência e campos descritivos. Não aceita proprietário, número, status nem título pai
fornecidos pelo cliente. Retorna o título com as parcelas carregadas.

`TitleService::settle(Title $titulo, TitleInstallment $parcela, array $dados): TitleSettlement`
recarrega os registros do usuário, aplica Gate, bloqueia usuário/título/parcelas e persiste
baixa, formas, estados e próxima recorrência na mesma transação, com até três tentativas
em erros de concorrência reconhecidos pelo Laravel.

O pedido exige `idempotency_key` UUID, `settlement_date`, `original_value` (principal desta
baixa) e 1–20 `payment_methods`, cada uma com `payment_type_id`, `financial_account_id`
e `value`. `discount`, `interest`, `fine` usam zero por padrão. `total_value` é opcional:
se informado, deve corresponder exatamente ao total calculado. Valores devem ser strings
com ponto decimal ou inteiros representando unidades monetárias, nunca `float`.
Principal e total são positivos; desconto não excede o principal. Baixa com total zero
não é suportada nesta etapa. A data não pode anteceder a emissão.

```php
use App\Services\TitleService;
use Illuminate\Support\Str;

// Executar no contexto de usuário autenticado, com conta e forma já cadastradas.
$service = app(TitleService::class);
$titulo = $service->create([
    'title_type' => 'receivable',
    'description' => 'Recebimento',
    'original_value' => '100.00',
    'issue_date' => '2026-10-01',
    'due_date' => '2026-10-31',
    'financial_account_id' => $conta->id,
]);
$pedido = [
    'idempotency_key' => (string) Str::uuid(), // gerar uma vez por operação
    'settlement_date' => '2026-10-15',
    'original_value' => '40.00',
    'payment_methods' => [[
        'payment_type_id' => $forma->id,
        'financial_account_id' => $conta->id,
        'value' => '40.00',
    ]],
];
$baixa = $service->settle($titulo, $titulo->installments->first(), $pedido);
// Reenvio do mesmo pedido retorna a mesma baixa, sem outro pagamento.
$mesmaBaixa = $service->settle($titulo, $titulo->installments->first(), $pedido);
```

O saldo de principal é valor da parcela menos principal das baixas anteriores,
independentemente de desconto/juros/multa. Após baixa parcial, o título fica `partial`;
a parcela permanece `pending`/`overdue` até liquidar todo o principal. O título fica `paid`
somente quando todas as parcelas estiverem pagas.

O UUID é único por título. O hash inclui parcela, valores normalizados, data, observações
e ordem das formas. Mesma chave com conteúdo diferente é rejeitada, mesmo após pagamento.
UUID/hash não são mass assignable; baixas legadas com campos nulos não são convertidas.
Nova chave representa uma nova operação: não gere outro UUID automaticamente ao repetir
uma tentativa de rede.

`TitleService::cancel(Title $titulo): Title` cancela o título e suas parcelas sem exclusão.
Com qualquer baixa ativa, o cancelamento é bloqueado; após estornar todas, pode cancelar
preservando histórico. Repetir cancelamento sem baixas ativas é permitido.
Há edição controlada por `update()` e estorno por `SettlementReversalService::reverse()`.

`Money::cents()` e `Money::decimal()` implementam aritmética não negativa exata até
`DECIMAL(15,2)`. Parcelamento distribui o resto de centavos nas primeiras parcelas;
datas são calculadas a partir do vencimento original, sem overflow de fim de mês.

Os Services exigem identidade autenticada. Jobs futuros deverão oferecer um contexto
explícito validado; não desabilite o escopo global na interface.
O lock do usuário serializa as operações financeiras do mesmo proprietário. Todas as
rotinas futuras que gerarem números ou alterarem principal deverão seguir esse protocolo.
A numeração lê os números existentes; uma tabela de sequência poderá ser necessária
se o volume crescer. Disputas entre processos foram verificadas; carga prolongada ainda não.
PDF está instalado e é emitido separadamente após o commit. `generate_receipt=true`
na baixa continua rejeitado para preservar a transação financeira e os hashes de pedidos antigos.

### Edição Controlada de Títulos

`TitleService::update(Title $titulo, array $dados): Title` recarrega o título do autenticado,
aplica Gate e mantém o protocolo de lock do usuário/título. Número, tipo, proprietário,
quantidade e IDs das parcelas são imutáveis por essa API.
Sem baixas, aceita valor, datas, contato/categoria/conta, recorrência e metadados.
Recalcula valores/datas das parcelas existentes, sem apagar registros.
Parcelamentos inconsistentes ou parcelas com baixas são rejeitados.

Com qualquer baixa, permite somente `description`, `document_number`, `reference` e `notes`.
Títulos cancelados não podem ser editados. Configuração de recorrência não pode ser alterada
em ciclos que já pertençam a uma cadeia. Desativar a recorrência antes da cadeia exige
também limpar tipo/dia/mês no pedido.
Vínculos não enviados são mantidos, mesmo inativos; vínculos enviados precisam ser ativos,
compatíveis e do proprietário. `null` desvincula contato/categoria/conta antes de baixas.

```php
$titulo = app(\App\Services\TitleService::class)->update($titulo, [
    'original_value' => '120.00',
    'due_date' => '2026-11-30',
]);
```

### Estorno Auditável

`SettlementReversalService::reverse(TitleSettlement $baixa, array $dados): SettlementReversal`
estorna **toda a baixa escolhida**, inclusive seus ajustes/formas, sem alterar seus valores.
O título pode continuar parcial se houver outras baixas ativas. Não há estorno parcial
de uma única baixa, desfazer estorno ou estorno automático em cascata nesta API.

Exige autenticado, Gate `reverse`, UUID, `reversal_date` em `Y-m-d` e motivo de
3–2000 caracteres. A data não pode ser anterior à baixa nem futura.
Campos extras/IDs do cliente são rejeitados. O proprietário/autor é o usuário autenticado.
O Service recarrega baixa/título/parcelas do dono, bloqueia o usuário e persiste
auditoria, invalidação de recibos, cancelamento de próximo ciclo e estados numa transação.
Erros revertem tudo, incluindo a tentativa de cancelamento do ciclo.

```php
use App\Services\SettlementReversalService;
use Illuminate\Support\Str;

$pedido = [
    'idempotency_key' => (string) Str::uuid(), // gerar uma vez por estorno
    'reversal_date' => now()->toDateString(),
    'reason' => 'Pagamento lançado na conta incorreta',
];
$estorno = app(SettlementReversalService::class)->reverse($baixa, $pedido);
```

UUID é único por usuário; uma baixa possui no máximo um estorno.
Mesmo UUID/pedido retorna a auditoria existente, inclusive depois de uma nova baixa.
Alterar o pedido com a mesma chave ou tentar outro estorno da mesma baixa com nova chave
é rejeitado. `request_hash` é oculto, e o Model é totalmente protegido de mass assignment.
Policy não permite edição/exclusão genérica desse registro.

`TitleSettlement::active()` exclui estornos para saldo de principal **atual**.
O saldo das contas usa a data do estorno para preservar períodos anteriores.
Nova baixa não pode anteceder o último estorno da parcela e exige nova chave de baixa.
Reenviar a chave da baixa original retorna a operação antiga, agora estornada;
nunca gera silenciosamente um pagamento substituto.

Após estorno, parcelas voltam a `pending`/`overdue` conforme vencimento, preservando as
que ainda estejam integralmente liquidadas. O título fica `partial` com principal ativo,
ou `pending`/`overdue` sem ele. A edição financeira permanece bloqueada por qualquer
histórico de baixa, mesmo estornado; cancelamento passa a ser possível sem baixas ativas.
Títulos excluídos logicamente podem ser estornados pelo dono sem desfazer o soft delete.

Novos ciclos registram `generated_by_settlement_id` da baixa de fechamento.
Estornar uma baixa de um título fechado, mesmo uma baixa parcial anterior à de fechamento,
cancela os próximos ciclos rastreados **sem histórico**, sem apagar registros ou reutilizar número.
Nova liquidação gera outro ciclo e novo número. Ciclos com qualquer histórico de baixa,
inclusive baixas já estornadas, bloqueiam o estorno da origem nesta etapa.
Não se presume que a raiz identifique o predecessor imediato: filhos apontam à raiz,
enquanto a nova FK identifica precisamente a baixa de origem.

Recorrência legada paga sem origem rastreável e baixa com valores/vínculos inconsistentes
exigem reconciliação, não backfill por suposição. Contas/formas desativadas podem integrar
o histórico do estorno; vínculos de outro dono não podem.
Recibo existente recebe `voided_at`, sem apagar arquivo ou número. Emissão/download
por `ReceiptService` rejeitam recibo invalidado e baixa estornada, com autorização.
Não há chamada bancária ou transferência real: este é um estorno contábil interno.

### Cadastros Financeiros

`CatalogService::create(string $model, array $dados)` e `update($registro, $dados)` aceitam
somente `Category`, `FinancialAccount`, `Contact` e `PaymentType`. Exigem autenticação,
validam campos/tamanhos, rejeitam chaves extras e IDs e usam transações com lock do usuário.
`delete(FinancialModel)` e `restore(FinancialModel)` fazem exclusão/restauração **lógica** dos
quatro cadastros, com lock do usuário e Gate `delete`/`restore`; não existe exclusão física.
`archive(Contact)` continua disponível e delega a `delete()`. Contatos podem ser excluídos com
histórico. Categorias, contas e formas com vínculos financeiros (títulos, inclusive excluídos,
parcelas ou formas de baixa) são recusadas com erro em `delete` e devem ser desativadas com
`active=false`; `blocksDeletion()` informa isso à interface antes do modal. Registros excluídos
não podem ser editados nem ativados/desativados sem restauração. Detalhes e justificativas em
[AUDITORIA_SOFTDELETE.md](AUDITORIA_SOFTDELETE.md).

Tipos ficam imutáveis após vínculos financeiros, incluindo títulos excluídos logicamente.
O saldo inicial aceita valores negativos, mas não pode mudar após movimentos de baixa.
Cor usa `#RRGGBB`; nomes são aparados e não podem ficar vazios; UF usa duas letras maiúsculas.
Não há validação de dígitos verificadores de CPF/CNPJ nesta etapa.
Contatos recebem `CLI`, `FOR` ou `CON` conforme client/supplier/both, com sequência por
proprietário e prefixo, incluindo soft deletes. O código não é informado nem alterado pela API.

```php
$catalog = app(\App\Services\CatalogService::class);
$conta = $catalog->create(\App\Models\FinancialAccount::class, [
    'name' => 'Conta corrente',
    'type' => 'checking',
    'initial_balance' => '-10.00',
]);
$conta = $catalog->update($conta, ['active' => false]);
```

### Saldo das Contas

`FinancialAccountService::balance(FinancialAccount $conta, ?string $data = null): array`
retorna `as_of`, `initial_balance`, `receipts`, `payments` e `balance`.
Valores são strings com duas casas, com sinal negativo permitido na abertura e saldo.
O cálculo usa `title_settlement_methods.value`, não principal do título: desconto/juros/multa
já estão refletidos no total efetivamente movimentado.

O corte é inclusivo por `settlement_date`, com hoje como padrão. Baixas futuras não entram
no saldo de hoje. Cada forma conta somente na conta em que foi registrada.
O histórico de títulos excluídos logicamente e contas desativadas permanece no saldo.
Não é previsão de títulos pendentes nem extrato completo. Baixas estornadas são excluídas
do corte somente quando `reversal_date <= data`; datas anteriores preservam a movimentação original.
Como não há data de abertura no schema, o saldo inicial é sempre aplicado ao corte informado.

O Service bloqueia usuário/conta, recarrega a propriedade e filtra também o proprietário
dos títulos no cálculo. Valores são somados em centavos, sem `SUM` aproximado no SQLite.
Totais de entradas/saídas e saldo são limitados a `DECIMAL(15,2)`; excesso gera erro explícito,
não arredondamento. Precisão máxima deve ser validada no MySQL, não no armazenamento SQLite.

### Inicialização de Cadastros

`FinancialDefaultsService::initialize(User $usuario, ?string $grupo = null)` é uma rotina
interna com proprietário persistido explícito, sem alterar a sessão.
Grupos válidos: `categories` e `payment_types`. Omitir grupo inicializa ambos atomicamente.
Nomes/campos estão em `config/financial_defaults.php`, conforme `DATABASE.md`.
Registros existentes com nome equivalente são preservados, mesmo desativados ou com tipo
personalizado. A comparação usa `LOWER` e a collation do banco; reexecução não sobrescreve.
Renomear um padrão permite que o nome original seja criado novamente em uma inicialização futura.

```powershell
# Inicializar um usuário existente; substituir o ID
php artisan finance:initialize 1

# Inicializar categorias/formas de todos os usuários existentes
php artisan db:seed
```

O `DatabaseSeeder` chama `CategorySeeder` e `PaymentTypeSeeder`, sem usuário de demonstração,
sem conta com saldo fictício e sem títulos. Seeders processam cada grupo/usuário por transação;
o comando direcionado inicializa ambos os grupos numa única transação.
Na integração MySQL, esses comandos são exercitados apenas dentro de testes com rollback.
Não rode `db:seed` manualmente em `gestao_financeira_testing`.
Não exponha Services internos por uma rota com ID fornecido pelo cliente: onboarding futuro
deve passar somente o usuário autenticado.

---

## 1. Filosofia do Projeto

O MoneyControl é um **assistente pessoal**, não um ERP. Isso guia todas as decisões:

- **Simplicidade para o usuário**, sem sacrificar poder
- Interfaces limpas, intuitivas, sem terminologia contábil desnecessária
- Cada ação deve exigir o **mínimo de cliques** possível
- O sistema deve **trabalhar pelo usuário** (notificações automáticas, recorrência automática)
- **Sem configurações desnecessárias** — boas práticas aplicadas por padrão

---

## 2. Arquitetura Geral

### Padrão adotado: **TALL Stack** (Tailwind + Alpine.js + Livewire + Laravel)

```
Browser ←→ Livewire Component (Blade + PHP) ←→ Service Layer ←→ Eloquent ←→ MySQL
```

- O **Livewire** gerencia todo o estado reativo da UI (sem necessidade de Vue/React)
- **Alpine.js** é usado apenas para microinterações (toasts, animações simples) — já incluído pelo MaryUI
- A **Service Layer** contém toda a lógica de negócio (nunca em Controllers ou Livewire diretamente)
- **Controllers** são mínimos — apenas para rotas que não usam Livewire (ex: download de PDF, exports)

### Fluxo de uma ação típica:

```
Usuário clica "Salvar Título"
    → Livewire valida os dados ($rules)
    → Livewire chama TitleService::create($data)
        → TitleService valida regras de negócio
        → TitleService inicia DB::transaction()
            → Cria Title
            → Cria TitleInstallments
            → Registra configuração de recorrência (o próximo título só é criado na baixa total)
        → DB::commit()
    → Livewire dispara evento de sucesso
    → MaryUI Toast exibe "Título salvo com sucesso!"
    → Lista de títulos atualizada automaticamente
```

---

## 3. Livewire 3 — Guia Prático

### Estrutura de um componente Livewire neste projeto

```php
<?php
// app/Livewire/Titles/TitleList.php

namespace App\Livewire\Titles;

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\TitleService;
use App\Models\Title;

class TitleList extends Component
{
    use WithPagination;

    // Propriedades reativas (atualizam a UI automaticamente)
    public string $search = '';
    public string $statusFilter = '';
    public string $titleType = 'receivable'; // ou 'payable'
    public bool $showModal = false;
    public ?Title $selectedTitle = null;

    // Regras de validação
    protected function rules(): array
    {
        return [
            'selectedTitle.description' => 'required|string|max:255',
            'selectedTitle.original_value' => 'required|numeric|min:0.01',
            'selectedTitle.due_date' => 'required|date',
        ];
    }

    // Métodos de ação
    public function openSettlementModal(int $titleId): void
    {
        $this->selectedTitle = Title::where('user_id', auth()->id())->findOrFail($titleId);
        $this->showModal = true;
    }

    public function render(): \Illuminate\View\View
    {
        $titles = Title::query()
            ->where('user_id', auth()->id())
            ->where('title_type', $this->titleType)
            ->when($this->search, fn($q) => $q->where('description', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->orderBy('due_date')
            ->paginate(15);

        return view('livewire.titles.title-list', compact('titles'));
    }
}
```

### Dicas importantes

- **Sempre use `auth()->id()`** para filtrar dados — nunca confie em parâmetros da URL
- Use `#[Computed]` para propriedades derivadas que não precisam ser reativas
- Use `wire:model.live` apenas quando realmente precisar de atualização em tempo real (filtros de busca)
- Use `wire:model.blur` para campos de formulário comuns (reduz requests)
- **Paginação:** sempre use `WithPagination` e `paginate()` — nunca `get()` em listas grandes

---

## 4. MaryUI — Componentes Utilizados

O MaryUI fornece componentes Blade elegantes já integrados com Livewire 3 e Alpine.js.

### Componentes principais que usaremos

```blade
{{-- Layout principal com sidebar --}}
<x-mary-main full-width>
    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-200">
        <x-mary-menu>
            <x-mary-menu-item title="Dashboard" icon="o-home" link="/dashboard" />
            <x-mary-menu-item title="A Receber" icon="o-arrow-down-circle" link="/titulos/receber" />
            <x-mary-menu-item title="A Pagar" icon="o-arrow-up-circle" link="/titulos/pagar" />
        </x-mary-menu>
    </x-slot:sidebar>
    <x-slot:content>
        {{ $slot }}
    </x-slot:content>
</x-mary-main>

{{-- Campo de busca --}}
<x-mary-input
    wire:model.live.debounce="search"
    placeholder="Buscar título..."
    icon="o-magnifying-glass"
    clearable
/>

{{-- Tabela de dados --}}
<x-mary-table
    :headers="$headers"
    :rows="$titles"
    :sort-by="$sortBy"
    with-pagination
>
    @scope('cell_status', $title)
        <x-mary-badge :value="$title->status_label" :class="$title->status_color" />
    @endscope
    @scope('actions', $title)
        <x-mary-button
            icon="o-arrow-down"
            wire:click="openSettlementModal({{ $title->id }})"
            tooltip="Dar Baixa"
            class="btn-success btn-sm"
        />
    @endscope
</x-mary-table>

{{-- Modal de baixa --}}
<x-mary-modal wire:model="showModal" title="Realizar Baixa">
    {{-- Conteúdo do modal --}}
    <x-slot:actions>
        <x-mary-button label="Cancelar" wire:click="$set('showModal', false)" />
        <x-mary-button label="Confirmar Baixa" class="btn-success" wire:click="confirmSettlement" />
    </x-slot:actions>
</x-mary-modal>

{{-- Toast de notificação --}}
<x-mary-toast />

{{-- Gráfico --}}
<x-mary-chart wire:model="chartData" />
```

### Como exibir mensagens

O toast do MaryUI (`Mary\Traits\Toast`) **não é usado**: ele oculta o ícone em telas pequenas,
mostra uma mensagem por vez e não exibe mensagens de redirecionamento de controllers.
O padrão do projeto é `App\Support\Feedback` + `<x-feedback />` (seção 16):

```php
use App\Support\Feedback;

// Componente Livewire: renderiza na mesma requisição
Feedback::now('created', 'Título cadastrado com sucesso!', 'REC-0001 · Aluguel');
// Controller com redirect: próxima requisição
Feedback::flash('success', 'Notificação marcada como lida com sucesso!');
```

---

## 5. Tema Dark/Light com Tailwind

O projeto usa **daisyUI 5**, instalado por npm, com MaryUI 2.9 e Tailwind 4.
Não há `tailwind.config.js`; a configuração implementada é CSS-first em
`resources/css/app.css`:

```css
@import 'tailwindcss';
@plugin "daisyui" {
    themes: light, dark --default;
}
@source '../../vendor/robsontenorio/mary/src/View/Components/**/*.php';
```

O layout `resources/views/components/layout.blade.php` lê `auth()->user()->theme`
para definir `data-theme` em cada página autenticada. A rota `/preferencias` monta
`UserPreferences`, que chama `UserPreferencesService` em transação e aceita somente
`dark`/`light` e 0 a 365 dias. A alteração de tema é aplicada nas próximas páginas
abertas; não há preferência duplicada no navegador.

A variante `dark:` do Tailwind foi redefinida em `app.css` para seguir o `data-theme` do
daisyUI (`@custom-variant dark (&:where([data-theme=dark], [data-theme=dark] *))`). Sem isso,
classes `dark:` seguiam o tema do sistema operacional, e não a preferência do usuário.

---

## 6. Módulo de Títulos — Regras de Negócio

### Estados de um título

```
pending (Pendente)
    → paid (Pago) — ao dar baixa total
    → partial (Parcial) — ao dar baixa parcial
    → overdue (Vencido) — automaticamente quando due_date < hoje e status = pending
    → cancelled (Cancelado) — cancelamento manual

partial (Parcial)
    → paid (Pago) — ao dar baixa da parcela restante
    → cancelamento bloqueado enquanto houver baixas ativas
```

### Geração automática do número do título

Implementado internamente em `TitleService::create()`: bloqueio do usuário,
leitura com lock dos números existentes (inclusive soft deleted), maior sufixo `REC`/`PAG`
e gravação antes do commit. A unicidade `(user_id, title_number)` permanece como proteção
do banco. Números não são reservados por um método público fora da transação.

### Criação de parcelas

```php
// Ao criar um título com 3 parcelas de R$ 300 (total R$ 900)
// O sistema cria automaticamente 3 TitleInstallment:
// Parcela 1 — R$ 300 — vencimento: due_date
// Parcela 2 — R$ 300 — vencimento: due_date + 1 mês
// Parcela 3 — R$ 300 — vencimento: due_date + 2 meses
```

### Atualização automática do status "Vencido"

Implementado em `OverdueTitleService::updateFor(User $usuario, ?string $data = null)`,
chamado por `UpdateOverdueTitles`. Remove `OwnerScope` somente nas consultas internas
com proprietário explícito, sem alterar a autenticação. Bloqueia o usuário durante a transação.

Marca parcelas `pending` com vencimento anterior ao corte como `overdue`.
Marca títulos `pending` como `overdue` quando tiverem parcela não liquidada vencida.
Títulos `partial` mantêm o estado; suas parcelas pendentes podem ficar vencidas.
Não modifica pagos, cancelados, títulos excluídos logicamente nem o vencimento do próprio dia.
Reexecução é segura e retorna quantidade de estados alterados.

```powershell
php artisan titles:update-overdue
php artisan titles:update-overdue --user=1 --date=2026-10-15
php artisan schedule:list
```

Sem `--user`, processa usuários em lotes, com transações separadas por proprietário.
IDs inexistentes e datas inválidas retornam falha antes da escrita.

```php
// routes/console.php: agenda implementada
use Illuminate\Support\Facades\Schedule;

Schedule::command('titles:update-overdue')->dailyAt('00:05')->withoutOverlapping();
Schedule::command('notifications:send-due-soon')->dailyAt('08:00')->withoutOverlapping();
```

A agenda usa o fuso da aplicação, atualmente UTC. Definir o fuso antes da implantação.
Para execução contínua local, iniciar `php artisan schedule:work`; em produção, configurar
`php artisan schedule:run` a cada minuto. O cadastro da agenda não configura o Windows
nem inicia processo automaticamente. O comando de alertas internos às 08:00 está implementado;
não envia e-mails, push ou SMS.

---

## 7. Sistema de Baixas

A baixa é a operação mais crítica do sistema. **Sempre executada dentro de DB::transaction()**.

### Fluxo completo de uma baixa

Implementado em `TitleService::settle()`, conforme contrato em
[Services Financeiros Entregues](#services-financeiros-entregues):

1. Validar o pedido, UUID, datas e valores em centavos.
2. Bloquear usuário, título e parcela; recarregar os registros do dono e aplicar Gate.
3. Se a chave já existir, comparar hash e retornar a mesma baixa ou rejeitar divergência.
4. Validar parcelamento, saldo restante, tipos/atividade das formas e contas.
5. Gravar baixa e formas cuja soma seja exatamente igual ao total calculado.
6. Atualizar estados de parcela e título de acordo com o principal liquidado.
7. Criar o próximo ciclo somente na liquidação total de um título recorrente.
8. Confirmar tudo na mesma transação; qualquer falha reverte pagamento e recorrência.

Não há tolerância de um centavo nem cálculos com `float`. Recibos ainda estão pendentes.

### Modal de baixa — Componente Livewire

Implementado como formulário em `TitleDetail` (ver [Operações Financeiras na Interface](#operações-financeiras-na-interface)):
data (padrão hoje), principal, desconto/juros/multa, até 20 formas, prévia de total/formas e
mesmo UUID em reenvios. O envio ("Revisar e confirmar baixa") abre o modal "Confirmar baixa?";
somente a confirmação chama o `TitleService`. Recibo PDF é emitido separadamente no histórico
(sem checkbox na baixa). A partir da listagem, o gesto para a direita abre este formulário.

---

## 8. Títulos Recorrentes

### Conceito

Um título recorrente é aquele que se repete indefinidamente. Diferente de parcelamentos (que têm fim), a recorrência não tem data final (ex: mensalidade da academia, aluguel, assinatura de streaming).

### Como funciona no sistema

1. Usuário cadastra o título com `is_recurring = true`, `recurrence_type = monthly`, `recurrence_day = 10`
2. O título aparece normalmente na lista para o mês atual
3. Ao liquidar **todo o principal** do título:
   - O `RecurrenceService` calcula a próxima data de vencimento
   - O `TitleService` cria automaticamente um **novo título** com `parent_title_id` apontando para o original
   - O novo título herda todas as configurações (descrição, valor, categoria, conta, contato)
4. O novo título aparece na lista do próximo mês, pronto para nova baixa

### RecurrenceService

`RecurrenceService::nextDueDate(Title $titulo): CarbonImmutable` é um cálculo puro:
usa o vencimento nominal do título, não a data em que ele foi pago. Avança um mês/ano,
aplica dia/mês configurados e limita o dia ao último válido do mês. Janeiro/31 gera
fevereiro/28 e, mantendo `recurrence_day=31`, o ciclo seguinte gera março/31.
Recorrência anual em fevereiro/29 volta a fevereiro/29 no próximo ano bissexto.

`RecurrenceService::firstDueDate($emissao, $tipo, $dia, $mes)` calcula o primeiro vencimento
na inclusão: a primeira data igual ou posterior à emissão com o dia (e o mês, na anual)
configurado, limitada ao último dia do mês (dia 31 em fevereiro → 28/29). Emissão fora de
`Y-m-d`, dia fora de 1–31, mês fora de 1–12 ou tipo inválido geram `ValidationException`
nos campos `issue_date`, `recurrence_day`, `recurrence_month` ou `recurrence_type`.

O `TitleService` persiste o próximo título e uma parcela na transação da baixa completa,
com nova numeração, vínculo direto à raiz e referência `m/Y`. Recorrência exige uma parcela
por título, dia válido e mês na anual. Baixa parcial não cria outro título.
Cada pagamento completo gera somente o próximo ciclo, mesmo que já esteja vencido;
não há geração automática de vários ciclos atrasados.

A emissão do próximo título usa a menor data entre hoje e seu vencimento, para não
criar vencimento anterior à emissão após pagamento atrasado. O novo título começa
`pending`; a atualização geral de vencidos ocorre na execução de `titles:update-overdue`.

Vínculos históricos do mesmo dono são herdados, inclusive contato excluído logicamente
e cadastros desativados. Isso não permite selecionar tais cadastros em uma criação manual
nova, que exige registros ativos. Todas as referências continuam limitadas ao proprietário.

---

## 9. Sistema de Notificações

### Como as notificações são geradas

Implementado em `NotificationService::generateFor(User $usuario, ?string $data = null)`.
Rotina interna com proprietário persistido explícito, lock do usuário e transação,
sem alterar sessão. Remove escopos apenas nas consultas filtradas pelo proprietário.
O corte usa hoje por padrão; datas inválidas/futuras são rejeitadas.

Considera parcelas `pending`/`overdue` de títulos não excluídos com estado
`pending`/`overdue`/`partial`. Inclui vencidas e próximas até a antecedência do usuário
(padrão 7; intervalo aceito 0–365). Parcelas pagas/canceladas não geram alertas.

Chave SHA-256 de tipo de evento, parcela e data de vencimento, com índice único por
usuário. Há um alerta `due-soon` e um `overdue` por vencimento, não um alerta por dia.
Reagendar a parcela permite um novo alerta e preserva o anterior no histórico.
Pagamento/cancelamento posteriores não apagam alertas já criados.
Antecedência 0 inclui hoje e vencidas. A severidade é `danger` para hoje/vencidas e
`warning` para futuras na janela. Não há e-mail, push ou SMS.

```powershell
php artisan notifications:send-due-soon
php artisan notifications:send-due-soon --user=1 --date=2026-10-16
```

Sem usuário, processa todos em lotes, com transação por proprietário. A agenda roda
às 08:00 com `withoutOverlapping()`, no fuso da aplicação (atualmente UTC).
Reexecução segura, inclusive entre processos. Não exponha `generateFor()` em rota
que aceite arbitrariamente um proprietário do cliente.

`markRead(Notification)` exige autenticado e recarrega a propriedade; preserva o
primeiro `read_at`. `markAllRead()` marca somente notificações não lidas do autenticado.
`PATCH /notificacoes/{notification}/ler` usa middleware `auth` e retorna ID/data de leitura.
`POST /notificacoes/atualizar` chama `generateFor(auth()->user())`, não recebe ID de
proprietário e retorna ao histórico com a quantidade de alertas novos. Isso permite
verificar vencimentos e atrasos imediatamente quando o scheduler local não está ativo;
o comando agendado continua responsável pela atualização automática às 08:00.

### Exibindo notificações no layout

Interface abaixo ainda planejada; o Service/rota de leitura já existem.

```blade
{{-- No header do layout principal --}}
<x-mary-dropdown>
    <x-slot:trigger>
        <x-mary-button icon="o-bell" class="btn-ghost btn-circle">
            @if($unreadCount > 0)
                <x-mary-badge value="{{ $unreadCount }}" class="badge-error badge-xs" />
            @endif
        </x-mary-button>
    </x-slot:trigger>

    @foreach($notifications as $notification)
        <x-mary-menu-item
            :title="$notification->title"
            :subtitle="$notification->message"
            wire:click="markAsRead({{ $notification->id }})"
        />
    @endforeach
</x-mary-dropdown>
```

---

## 10. Gerador de Recibos PDF

### Configuração do DomPDF

Dependência de produção instalada: `barryvdh/laravel-dompdf` 3.1.2 e
`dompdf/dompdf` 3.1.6, fixadas pelo lock. Instalar o projeto com `composer install`.
O Service desativa PHP, JavaScript e recursos remotos. Não há imagens externas no template.
Cache/temporários do renderizador usam o diretório temporário do sistema.

Template real: `resources/views/receipt.blade.php`. Usa snapshot, campos escapados pelo
Blade e valores formatados em centavos, sem conversão monetária por `float`.
É comprovante interno da baixa (pagamento ou recebimento), não documento fiscal.

### ReceiptService

`ReceiptService::generate(TitleSettlement $baixa): Receipt` exige autenticado, Gate e
baixa não estornada, com data até hoje, vínculos/valores coerentes do proprietário.
Deve ser chamado **depois do commit da baixa**, sem transação externa. A opção
`generate_receipt=true` no `TitleService` continua rejeitada; não altera hashes antigos.

```php
$recibo = app(\App\Services\ReceiptService::class)->generate($baixa);
```

Usuário e sequência global `document_sequences.receipts` são bloqueados até o commit.
Numeração `RCB-AAAA-000001`, com sufixo global contínuo (não reinicia no ano).
A migration inicializa o contador a partir de números legados reconhecíveis, sem
alterar recibos existentes. A sequência suporta sufixo até 11 dígitos.
Unicidade de baixa/número também é garantida no banco.

O PDF fica no disco `receipts`, raiz `storage/app/receipts-private`, caminho
`users/<id>/<uuid>.pdf`, sem `serve`, link público ou disco configurável pelo cliente.
A raiz fica fora dos discos `local` e `public`, evitando o acesso por URLs temporárias
do disco local genérico.
O snapshot preserva dados da emissão e `pdf_sha256` valida integridade.
Alterar nomes/descrição depois não muda o documento já emitido.
Repetir emissão retorna o mesmo recibo/arquivo, sem consumir outro número.
PDF ausente retorna 404; hash divergente, invalidação ou legado sem snapshot/hash são
rejeitados. Não há regeneração silenciosa.

Falha normal de renderização/gravação/persistência reverte registro/contador e tenta
remover somente o arquivo novo. Erro de cleanup é reportado sem ocultar o erro original.
Banco e filesystem não têm commit atômico: encerramento abrupto pode deixar arquivo
órfão. Recuperação/reconciliação explícita continua pendente. Nenhum arquivo de recibo
é apagado pelo estorno, apenas invalidado.

### Download do PDF

Rotas `auth`, Controllers mínimos e verificação no Service:

| Método | URL | Nome |
|---|---|---|
| POST | `/baixas/{settlement}/recibo` | `receipts.store` |
| GET | `/recibos/{receipt}/download` | `receipts.download` |

Emissão retorna JSON com ID, número e URL autorizada; download retorna PDF com
`Cache-Control: private, no-store` e `X-Content-Type-Options: nosniff`.
Recarrega proprietário e valida estorno/invalidação/hash/caminho sob lock, lendo o arquivo
antes de liberar a transação. Acesso de outro usuário retorna 404; hóspede JSON recebe 401.
HTML de hóspede redireciona para `/entrar`, a rota `login` implementada.
Não existem URLs públicas assinadas
que contornem a validação posterior ao estorno.

---

## 11. Importação e Exportação de Planilhas

### Configuração do Laravel Excel

```bash
composer require maatwebsite/excel
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

### Export de títulos

```php
// app/Exports/TitlesExport.php

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TitlesExport implements FromCollection, WithHeadings
{
    public function __construct(private string $type) {}

    public function collection()
    {
        return Title::where('user_id', auth()->id())
            ->where('title_type', $this->type)
            ->with(['contact', 'category'])
            ->get()
            ->map(fn($t) => [
                'Número'      => $t->title_number,
                'Descrição'   => $t->description,
                'Contato'     => $t->contact?->name,
                'Categoria'   => $t->category?->name,
                'Valor'       => $t->original_value,
                'Vencimento'  => $t->due_date->format('d/m/Y'),
                'Status'      => $t->status_label,
            ]);
    }

    public function headings(): array
    {
        return ['Número', 'Descrição', 'Contato', 'Categoria', 'Valor', 'Vencimento', 'Status'];
    }
}
```

### Import de títulos

```php
// app/Imports/TitlesImport.php
// A validação de cabeçalhos e mapeamento de colunas deve ser robusta
// Usar WithValidation para garantir dados corretos antes de importar
```

---

## 12. Dashboard — Gráficos, KPIs e Relatório

`FinancialDashboard` atende `/dashboard-financeira` e delega toda leitura a
`FinancialAnalyticsService`. O filtro inicial é o primeiro/último dia do mês atual;
`clearFilters()` restaura essas datas, remove restrições e fecha o relatório.

### Base de cálculo

A análise não soma `title_settlements.total_value`, porque uma baixa pode ter várias
formas e contas. Ela lê `title_settlement_methods.value`, junta título, conta, forma,
categoria e contato sob o mesmo proprietário e soma em centavos PHP:

- Baixa dentro do período: valor positivo na `settlement_date`.
- Estorno dentro do período: mesmo valor negativo na `reversal_date`.
- Uma baixa/estorno conta como um lançamento, mesmo com várias formas; os valores seguem
  divididos pelas contas/formas efetivamente usadas.
- Saldo inicial de conta e títulos sem baixa não entram nos KPIs, no gráfico de entradas
  e saídas, nas distribuições nem no relatório: essa é uma análise realizada, não previsão.
  Apenas o histórico `accounts` inclui saldo inicial; ele também segue todos os filtros.

Datas inicial/final são inclusivas. Categorias, formas e contas usam chips de inclusão:
todos marcados é consolidado; desmarcar um transforma-o em exclusão. Chips e contatos
entram em vigor no clique (`toggle()`), mas reutilizam as datas e o agrupamento **já
aplicados**: datas digitadas só valem em **Aplicar filtros**/**Gerar relatório**, e uma
data pendente inválida não bloqueia os chips. A busca de contatos filtra a lista no
navegador (`wire:model` diferido + `x-show`), sem refazer a análise a cada tecla.
`FinancialDashboard::$result` guarda o resultado calculado na ação para que `render()` não
repita a consulta na mesma requisição. Contatos usam
seleção múltipla inclusiva, com busca; uma seleção vazia inclui todos. IDs são validados
como pertencentes ao autenticado. Para proteger a renderização, a consulta limita-se a
10.000 formas por análise e retorna validação solicitando filtros mais restritos acima
desse limite.

### Indicadores, gráficos e relatório

**Campos de data:** use `<x-date-input>` para qualquer data editável. Ele renderiza o
controle nativo `type="date"` com `lang="pt-BR"`, preservando o calendário e solicitando
ao navegador a apresentação brasileira. O estado Livewire permanece em `yyyy-mm-dd`,
formato esperado pelos Services e validações. Passe `model` com o caminho Livewire e
`value` com o valor ISO atual. Use `live` somente quando a tela precisar recalcular
prévias enquanto a data é alterada; os demais campos mantêm sincronização diferida
até a ação Livewire. `max` aceita uma data ISO. Datas de leitura devem ser formatadas
como `d/m/Y`.

Os KPIs (`.kpi-card`) são entradas, saídas, saldo líquido (verde/vermelho pelo sinal) e
quantidade de eventos. O gráfico temporal mantém todos os dias do período, inclusive dias
sem movimentação. As distribuições por categoria/forma vêm ordenadas pela maior
participação (`|entradas| + |saídas|`, empate por nome) e mostram barras e percentuais
separados para entradas e saídas; participações acima de zero e abaixo de 1% aparecem
como `<1%`. A tabela detalhada usa `movement_days`, somente datas com eventos, sem excluir
dias de líquido zero após estorno, com datas `d/m/Y` e coluna de saldo do dia.
`daily` mantém o período inteiro.

**Gráficos (SVG + Alpine, sem biblioteca externa):**

- `financial-timeline.blade.php`: entradas e saídas diárias. Escala Y "redonda"
  (passos 1/2/2,5/5 × 10ⁿ, ~4 intervalos, inclui zero e negativos) com rótulos compactos
  em uma unidade única (`R$`, `mil`, `mi`); linha do zero destacada. Tooltip com strings
  monetárias formatadas no servidor, entradas, saídas e saldo do dia; acompanha o ponto
  mais alto da data e aparece abaixo dele quando não há espaço acima. Seleção por posição,
  toque e setas do teclado.
- `account-balance-chart.blade.php`: uma linha por conta com o saldo ao fim de cada dia.
  A escala Y é recalculada no navegador apenas com as contas selecionadas nos mini cards
  (todas marcadas por padrão, ações **Todas**/**Nenhuma**). Cada card mostra saldo final e
  variação no período. Tooltip lista as contas da maior para a menor e o total selecionado.
- `App\Support\ChartAxis` centraliza a posição X dos pontos e os até 7 rótulos de data
  (`d/m`, ou `d/m/y` quando o período cruza anos), compartilhados pelos dois gráficos.
- Ambos usam a área de plotagem 960×300 (margem esquerda 88), linhas de 2px com
  `vector-effect="non-scaling-stroke"`, grade de 1px e cores do tema daisyUI. Em telas
  estreitas o gráfico tem largura mínima de 720px e rola na horizontal, mantendo os eixos
  legíveis. Floats são usados apenas para coordenadas do desenho, nunca para totais.

**Histórico de saldo por conta (`accounts`):** `FinancialAnalyticsService::accountBalances()`
calcula saldo inicial + recebimentos − pagamentos, com estorno na própria data.
Aplica os filtros principais de conta, categoria, forma e contato tanto aos movimentos
anteriores ao período que formam `opening` quanto aos movimentos da série `values`
(um valor por dia de `daily`). As datas são inclusivas e movimentos posteriores ao fim
não entram. O saldo inicial é preservado, pois não possui categoria, forma ou contato.
Com filtros restritivos, a tela informa que o resultado é **saldo analítico**, não
saldo bancário completo; `FinancialAccountService::balance()` continua calculando o
saldo integral, sem mudança de contrato. Abertura e cada saldo diário são validados
nos limites de `Money`, em centavos inteiros. Os mini cards só controlam a visibilidade
local das linhas, sem substituir os filtros principais.
Conta inativa só sai do gráfico se não movimentou no período
**e** está zerada. A cor é a cadastrada na conta; cores repetidas (ex.: o padrão
`#8b5cf6`) recebem o próximo tom livre de uma paleta categórica fixa.

**Padrão visual:** seções usam `.panel` + `.section-header`/`.section-title` (ícone MaryUI)
/`.section-subtitle`; filtros usam `.filter-chip`/`.is-on` com ícone de estado; o período
aplicado aparece em `.period-badge`; distribuições usam `.share-item`/`.share-row`.
Classes definidas em `resources/css/app.css`. Evite misturar `@php(...)` inline com blocos
`@php ... @endphp` no mesmo template: o compilador do Blade pode engolir o conteúdo entre
eles (gera "Undefined variable"); use sempre a forma em bloco.

`generateReport()` reaplica os filtros e mostra uma visão imprimível. Os agrupamentos
suportados são `Conta > Contato > Data`, `Conta > Data` e `Conta > Categoria > Data`.
Cada nível recebe entradas, saídas e saldo subtotal; as folhas exibem data, evento,
título, contato/categoria, forma e valores. O relatório não mostra prefixos técnicos
como “Nível”: cada agrupamento recebe apenas seu nome. O cabeçalho contém período,
parâmetros e quatro cards de KPI (entradas, saídas, saldo e quantidade); subtotais e
rodapé têm destaque próprio. O rodapé identifica MoneyControl, usuário/e-mail solicitante
e data/hora de emissão. O tema de impressão força uma página branca, preserva o cabeçalho
do relatório (ocultando somente a navegação da aplicação), aplica cabeçalho azul, cores
financeiras, tabela com zebra, cabeçalhos escuros e repetição de `thead`, preservando
leitura em PDF.
Não ocultar `main > :not(.financial-report)`: o relatório é descendente do contêiner
Livewire, e essa regra torna toda a impressão vazia. A classe explícita
`.financial-dashboard__controls` oculta os controles sem esconder o ancestral do relatório.
Grupos podem atravessar páginas; linhas da tabela continuam protegidas contra quebra.
O botão **Imprimir / salvar PDF** usa o diálogo do navegador: não cria arquivo nem recibo
no servidor.

O total do relatório usa o rótulo **Saldo líquido do período** (entradas − saídas do
período), não saldo final de conta.

`FinancialDashboardTest` cobre valores divididos por forma, filtros combinados, estorno
em período diferente, limites inclusivos, cadastros históricos, isolamento de proprietário,
todos os agrupamentos, reset, chips sem aplicar datas pendentes, ordenação das
distribuições e histórico de saldo por conta (abertura filtrada, saldo inicial preservado,
filtros individuais/combinados, contatos múltiplos, estorno de baixa anterior, limites
de datas, conta excluída e conta inativa com saldo): **18 testes / 125 assertions**.
`MySqlFinancialAnalyticsTest` confirma precisão decimal, abertura filtrada, datas de
estorno e filtros de categoria, forma, contato e conta no banco MySQL de testes.

**Textos da interface (24/10/2026):** revisados rótulos, navegação, formulários,
mensagens próprias dos Services, relatório e recibos em português brasileiro.
Identificadores técnicos e dados persistidos não devem ser corrigidos por substituição
ortográfica. Testes que verificam texto em resposta Livewire devem comparar o HTML
decodificado (`$response->json('components.0.effects.html')`), não o JSON bruto com
Unicode escapado. Esta revisão não adiciona tradução integral das regras nativas Laravel.

---

## 13. Segurança e Isolamento de Dados

> **REGRA MAIS IMPORTANTE DO SISTEMA:** Nunca confiar em IDs vindos da URL ou de inputs.

### Policy para títulos

`TitlePolicy` está registrada no provider e verifica o proprietário persistido,
inclusive se os atributos locais do objeto forem modificados. O exemplo abaixo é resumido:

```php
// app/Policies/TitlePolicy.php

class TitlePolicy
{
    public function view(User $user, Title $title): bool
    {
        return $user->exists && $title->belongsToOwner($user);
    }

    public function update(User $user, Title $title): bool
    {
        return $this->view($user, $title);
    }

    public function delete(User $user, Title $title): bool
    {
        return $this->view($user, $title);
    }
}
```

### Uso obrigatório nos componentes Livewire

```php
// CORRETO ✅
$title = Title::where('user_id', auth()->id())->findOrFail($id);

// ERRADO ❌ — nunca fazer assim (qualquer usuário pode ver dados de outro)
$title = Title::findOrFail($id);
```

### Escopo de Proprietário Implementado

`FinancialModel` registra `App\Models\Scopes\OwnerScope`. O escopo retorna vazio quando
`auth()->id()` não existe, evitando acesso global acidental em requisições anônimas ou jobs.
Ele restringe Models com `user_id` diretamente e usa subconsultas por título/baixa para
parcelas, baixas e formas de pagamento. O binding de rota e as consultas relacionais usam
o mesmo filtro. `withTrashed()` não remove o filtro de proprietário.

Jobs e comandos sem sessão deverão fornecer um proprietário explicitamente. Somente código
interno confiável pode remover `OwnerScope`; deve então chamar `constrainToOwner()` no Builder
ou aplicar um filtro equivalente antes da consulta. Não use `withoutGlobalScopes()` na UI.

```php
// Padrão para futura rotina interna com usuário explícito, já autorizado.
$consulta = TitleSettlement::withoutGlobalScope(OwnerScope::class);
$consulta->getModel()->constrainToOwner($consulta, (int) $usuario->id);
$baixas = $consulta->get();
```

`Gate::authorize()` deve ser chamado antes de mutações e downloads. `FinancialRecordPolicy`
permite leitura ao dono, atualização de cadastros/notificações e soft delete/restauração de
categorias, contatos, contas e formas (dependências validadas no `CatalogService`). `TitlePolicy` permite atualização e soft delete/restauração do título do dono;
restrições financeiras adicionais serão responsabilidade do Service.
Baixas, parcelas, formas e recibos não têm atualização/exclusão genérica via Policy.
Exclusão física (`forceDelete`) é negada em todos os Models financeiros.
Não basta receber `create` da Policy: validar o usuário e todos os vínculos é obrigatório.

---

## 14. Convenções de Código

### Nomenclatura

| Tipo | Convenção | Exemplo |
|---|---|---|
| Model | PascalCase singular | `TitleSettlement` |
| Controller | PascalCase + Controller | `TitleController` |
| Livewire Component | PascalCase | `TitleList`, `SettlementModal` |
| Service | PascalCase + Service | `TitleService` |
| Migration | snake_case descritivo | `create_titles_table` |
| Tabela BD | snake_case plural | `title_settlements` |
| Coluna BD | snake_case | `due_date`, `user_id` |
| Rota | kebab-case | `/titulos-a-pagar` |
| View | snake_case | `title-list.blade.php` |

### Docblocks obrigatórios nos Services

```php
/**
 * Realiza a baixa de um título ou parcela específica.
 *
 * @param  Title             $title        Título a ser baixado
 * @param  TitleInstallment  $installment  Parcela a ser baixada
 * @param  array{
 *     idempotency_key:   string,
 *     settlement_date:   string,
 *     original_value:    string|int,
 *     discount?:         string|int,
 *     interest?:         string|int,
 *     fine?:             string|int,
 *     total_value?:      string|int,
 *     generate_receipt?: bool,
 *     payment_methods:   array<array{payment_type_id: int, financial_account_id: int, value: string|int}>,
 *     notes?:            string
 * } $data Dados da baixa
 *
 * @throws \Illuminate\Validation\ValidationException Se dados ou soma não conferirem
 * @throws \Illuminate\Auth\AuthenticationException Se não houver usuário autenticado
 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se o vínculo não for do dono
 * @throws \Throwable Em caso de falha na transação
 */
public function settle(Title $title, TitleInstallment $installment, array $data): TitleSettlement
```

---

## 15. Fluxo de Trabalho Git

```
main (produção)
 └── develop (desenvolvimento)
      └── feature/nome-da-funcionalidade
      └── fix/descricao-do-bug
```

### Commits

Usar **Conventional Commits** em português:

```
feat: adiciona modal de baixa de títulos
fix: corrige cálculo de saldo da conta financeira
docs: atualiza guia do desenvolvedor
refactor: extrai lógica de recorrência para RecurrenceService
chore: adiciona seeder de categorias padrão
```

---

## 16. Atalhos de Arraste, Confirmações e Mensagens

### Gestos nas listagens

`<x-swipe-row>` (`resources/views/components/swipe-row.blade.php`) + `swipeRow`
(`resources/js/swipe-row.js`, registrado em `app.js` no evento `alpine:init`; Alpine vem do
Livewire). Usa Pointer Events (mouse, touchpad e toque) com `touch-action: pan-y`:

- O eixo é decidido após 8px; movimento predominantemente vertical abandona o gesto (rolagem).
- Limite para reconhecer: 30% da largura da linha (mín. 72px, máx. 140px). Soltar antes, ou
  `Esc`, devolve a linha. Controles (`button`, `input`, `select`...) não iniciam arraste;
  o clique em link após arrastar é bloqueado.
- Durante o arraste, a faixa revelada mostra ícone, cor e rótulo da ação; após soltar, a
  linha fica "Processando operação..." até a resposta, impedindo disparo duplicado.
- O gesto só chama um método Livewire configurado (`method` + `args`). O servidor recarrega o
  registro do dono, revalida o estado e, se necessário, abre o modal. Nada é gravado pelo gesto.
- As dicas (`right`/`left`: `label`, `icon`, `tone`) são calculadas no servidor pelo estado
  atual; o tom `neutral` indica ação indisponível e o servidor informa o motivo ao soltar.

| Tela | Direita | Esquerda |
|---|---|---|
| `Titles` (`/receitas`, `/despesas`) | `startSettlement()`: título aberto com parcela pendente/vencida → redireciona a `?acao=baixa` | `startReversalOrCancellation()`: com baixa ativa → `?acao=estorno` (motivo/data obrigatórios); sem baixa → modal de cancelamento; cancelado → motivo |
| `Catalog` (`/cadastros/*`) | `edit()`: carrega o formulário e dispara `catalog-form-focus` (rola e foca) | `confirmDelete()`: modal de exclusão lógica; vínculos financeiros → motivo |

Os botões de cada linha chamam os mesmos métodos (alternativa sem gesto).
`<x-gesture-help context="titles|catalog" />` renderiza o botão flutuante de ajuda e o
tutorial (modal MaryUI com `<dialog>` nativo: Esc, botão fechar, foco inicial em "Entendi").

### Confirmação obrigatória

`App\Livewire\Concerns\ConfirmsOperations` + `<x-confirm-modal :confirmation="$confirmation" />`
(modal MaryUI com `wire:model="confirming"`):

- `askConfirmation($operacao, $alvo, $assunto)` grava o pedido em `$confirmation`
  (`#[Locked]`) e abre o modal. Fechar por Esc/X/fundo/"Cancelar" descarta o pedido.
- `confirmOperation()` chama o método mapeado em `confirmableOperations()`. Esse método
  chama `consumeConfirmation($operacao, $alvo)` **antes** do Service: sem pedido pendente
  igual, lança erro `confirmation` e nada é gravado. Clique repetido após concluir é ignorado.
- Falha de validação durante a confirmação fecha o modal e mostra a notificação de erro.
- Textos, ícones e botões ficam em `Feedback::CONFIRMATIONS`: `reverse` ("Estornar baixa?" /
  "Confirmar estorno"), `cancel` ("Confirmar cancelamento?"), `delete` ("Confirmar exclusão?"),
  `settle` ("Confirmar baixa?"), `discard-payment` e `discard-reversal`.

```php
use App\Livewire\Concerns\ConfirmsOperations;

protected function confirmableOperations(): array
{
    return ['delete' => 'delete'];
}

public function confirmDelete(int $id): void   // botão ou gesto
{
    $this->askConfirmation('delete', $id, 'Categoria · Lazer');
}

public function delete(int $id, CatalogService $service): void
{
    $this->consumeConfirmation('delete', $id);  // obrigatório antes de gravar
    $service->delete(Category::findOrFail($id));
    Feedback::now('deleted', 'Categoria excluída com sucesso!');
}
```

### Mensagens com ícone

`App\Support\Feedback::KINDS` define ícone (Heroicons via MaryUI) e tom: `created`
(o-plus-circle), `updated` (o-pencil-square), `deleted` (o-trash), `settled` (o-banknotes),
`reversed` (o-arrow-uturn-left), `cancelled` (o-no-symbol), `restored` (o-arrow-path),
`activated`/`deactivated`, `receipt`, `success`, `info`, `warning`, `error`.
`<x-feedback />` exibe a notificação flutuante (canto inferior direito, acima do botão de
ajuda) com ícone, texto, detalhe opcional e botão "Fechar mensagem"; sucesso fecha em 6s,
informação em 8s, aviso/erro só manualmente. Erro/aviso usam `role="alert"`; os demais,
`role="status"`. Ícones têm `aria-hidden`; o texto é sempre exibido. Erros de validação
aparecem no resumo com ícone (rolado para a área visível), exceto quando a notificação de
erro já mostrou o motivo. `session('status')` legado (autenticação) é exibido como informação.

Padrão dos textos: "[Entidade/Operação] … com sucesso!" somente após o retorno do Service,
por exemplo "Título cadastrado com sucesso!", "Baixa realizada com sucesso!", "Estorno
realizado com sucesso!", "Título cancelado com sucesso!", "Conta financeira alterada com
sucesso!", "Categoria excluída com sucesso!", "Contato restaurado com sucesso!".
`Catalog::ENTITIES` guarda nome singular e gênero de cada cadastro para essas mensagens.

### Layout das listagens

`.record-list` é um container (`@container`); as colunas usam `@3xl:` (largura da própria
lista, não da tela), porque o formulário lateral estreita a lista em telas médias. Abaixo
disso a linha empilha título, valores, status e ações. Classes em `resources/css/app.css`:
`.record-list`, `.record-row`, `.status-badge`, `.swipe-row*`, `.swipe-tone--*`,
`.feedback*`, `.confirm-modal*`, `.help-fab`, `.gesture-*`.
`Display::statusBadge()` fornece ícone/tom do status do título.

### Testes

`InteractionShortcutsTest` (21 testes): dicas por estado, redirecionamentos dos atalhos,
modal de cancelamento/estorno/exclusão, cancelamento do modal sem alterar dados, fechamento
pelo navegador, bloqueio de operação sem confirmação, revalidação de estado (baixa registrada
com o modal aberto), confirmação repetida, propriedade bloqueada, isolamento por dono,
mensagens, componente de notificação, exclusão lógica/restauração e regras de vínculos.
Validação de navegador (Edge headless, SQLite temporário fora do projeto): arraste curto,
Esc, modal, toasts, toque em 375px, rolagem vertical sem disparo, temas claro/escuro e
ausência de erros no console.

## 17. Checklist para Novas Funcionalidades

Antes de considerar uma funcionalidade pronta, verificar:

- [ ] Todos os campos validados no Livewire (`$rules`)
- [ ] Regras de negócio no Service, não no Livewire
- [ ] Operações críticas dentro de `DB::transaction()`
- [ ] Filtro `user_id` em todas as queries
- [ ] Soft delete implementado onde aplicável
- [ ] Tratamento de erros com mensagem de erro (`Feedback`/resumo de validação)
- [ ] Mensagem de sucesso com ícone após operação bem-sucedida
- [ ] Componente recebe apenas os dados necessários (sem over-fetching)
- [ ] Migração criada e testada
- [ ] Seeder atualizado se necessário
- [ ] Rota protegida com middleware `auth`
- [ ] Policy criada para operações sensíveis
- [ ] Estorno, cancelamento e exclusão passam por `ConfirmsOperations` (modal + consumo no servidor)
- [ ] Mensagens via `Feedback::now()`/`flash()` com tipo (ícone) adequado, só após sucesso no Service
- [ ] Listagens com ações frequentes avaliadas para `<x-swipe-row>` + botões equivalentes
- [ ] Atualizado o `AI_DEV_LOG.md` com o que foi implementado

---

*Documento gerado pelo agente IA em 14/10/2026 — versão 1.0*  
*Mantenha este documento atualizado a cada decisão técnica relevante.*
