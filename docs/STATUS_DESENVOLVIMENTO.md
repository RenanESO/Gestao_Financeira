# Status do Desenvolvimento

**Data:** 24/10/2026

## Atualização de Campos de Data

Os campos editáveis de data mantêm o seletor nativo de calendário (`type="date"`) com
`lang="pt-BR"`, solicitando ao navegador a apresentação brasileira. O estado e o valor
persistido continuam em `yyyy-mm-dd`; datas da baixa, estorno, emissão, vencimentos e
filtros da dashboard preservam as ligações Livewire anteriores. Conforme solicitado,
não foram executados testes. Build e compilação das views passaram como verificações
técnicas.

## Onde Paramos

Concluímos a **consolidação dos contatos, preferências e indicadores iniciais do painel**,
mantendo a integração das operações financeiras na interface de títulos:
baixa parcial/total, múltiplas formas, ajustes, estorno auditável, edição controlada,
cancelamento e emissão separada de recibos. Criação de recorrência mensal/anual
também está disponível. O domínio financeiro anterior foi preservado.
A próxima entrega deve refinar os formulários financeiros e a experiência em diferentes
viewports, sem refazer a autenticação, os cadastros ou as operações financeiras já entregues.
Nesta retomada, confirmamos a baixa e os alertas internos em execução isolada e
eliminamos os bloqueios operacionais que impediam o uso imediato pela interface.
Nesta etapa, completamos a prévia de conferência das baixas e a edição de recorrência
antes de qualquer histórico financeiro.
Também concluímos a dashboard financeira analítica e refinamos seu relatório para
impressão/salvamento em PDF, preservando o domínio financeiro e sem gravar documentos
no servidor.
Corrigida a impressão vazia causada pela ocultação do ancestral Livewire do relatório.
Gráfico mantém todo o período e possui tooltip por data; a tabela lista só dias com eventos.
Em 23/10/2026 a dashboard ganhou eixos X/Y nos gráficos, histórico de saldo por conta com
mini cards de seleção, layout padronizado (KPIs, chips, seções, distribuições) e correções
de lógica: chips não aplicam datas pendentes, análise não é repetida no render, conta
inativa com saldo continua no gráfico e o total do relatório passou a se chamar
"Saldo líquido do período".
Ainda em 23/10/2026 foram entregues as diretrizes de `Implementacaoes_Correcoes.md`:
atalhos de arraste em títulos (baixa / cancelamento ou estorno) e cadastros (editar /
excluir), modal de confirmação obrigatório com validação no servidor, notificações com
ícone por tipo de operação, botão flutuante de ajuda com tutorial dos gestos, exclusão
lógica de categorias/contas/formas (migration aditiva) e a auditoria de SoftDelete em
[AUDITORIA_SOFTDELETE.md](AUDITORIA_SOFTDELETE.md).
Em 24/10/2026 o histórico de saldo por conta passou a seguir todos os filtros principais
da dashboard, inclusive na abertura anterior ao período, preservando o saldo inicial.
A tela explica o saldo analítico quando há filtros restritivos. Também foi concluída
a revisão ortográfica dos textos próprios das telas, mensagens financeiras e recibos,
sem alterar identificadores técnicos nem dados existentes.

Já implementado:

- MySQL 8.0+ com 18 migrations aplicadas em `gestao_financeira`; em 24/10 o banco `gestao_financeira_testing` foi encontrado e recebeu a migration de SoftDelete pendente.
- Models, relacionamentos, casts, soft deletes, `OwnerScope` e Policies.
- `Money` com centavos exatos, incluindo valores assinados para saldo.
- `TitleService`: criação, parcelas, baixas parciais/totais, idempotência, cancelamento e edição controlada.
- `RecurrenceService`: datas mensais/anuais; próximo ciclo criado na transação da baixa.
- `FinancialAccountService::balance()`: saldo por conta e data, com abertura, recebimentos e pagamentos.
- `CatalogService`: criação/edição de categorias, contas, contatos e formas; arquivamento/restauração de contatos.
- `OverdueTitleService` e comando `titles:update-overdue`, com opção de usuário/data.
- `FinancialDefaultsService`, comando `finance:initialize` e seeders de categorias/formas.
- Agenda Laravel de vencidos às 00:05, com prevenção de sobreposição.
- `SettlementReversalService::reverse()`: estorno integral de uma baixa, com motivo, data, proprietário e UUID de idempotência.
- `SettlementReversal`: auditoria separada, sem apagar ou alterar valores da baixa e formas originais.
- Rastreabilidade de novos ciclos em `titles.generated_by_settlement_id`.
- Invalidação de recibos existentes via `receipts.voided_at`, preservando registro e caminho.
- Saldo por data respeitando o estorno, sem modificar o saldo anterior à sua data.
- `ReceiptService`: emissão separada da baixa, numeração global bloqueada, snapshot, SHA-256 e download privado.
- DomPDF instalado em produção; PDF sem execução PHP/JavaScript nem recursos remotos.
- `NotificationService`: alertas por parcela/vencimento, marcação de leitura individual e em lote.
- `notifications:send-due-soon` agendado às 08:00; sem e-mail, push ou SMS nesta etapa.
- Rotas autenticadas de emissão, download e marcação de leitura.
- Dez cenários de concorrência entre processos PHP independentes, com espera de lock confirmada no InnoDB.
- Livewire 3.8.10 e MaryUI 2.9 em produção; Tailwind 4, daisyUI 5, Vite 6 e plugin Laravel Vite 1.
- Login/logout, registro e recuperação de senha com recursos nativos do Laravel.
- Registro atômico com 12 categorias e 7 formas padrão; sem contas, saldos ou títulos fictícios.
- Proteção de login por e-mail/IP, throttling nas rotas de registro e recuperação e regeneração de sessão.
- Página de apresentação, layout com navegação lateral e menu recolhível, painel e alternância de tema na página.
- Cadastros Livewire: criação, edição, busca, paginação e ativação/desativação de categorias, contatos, contas e formas via `CatalogService`.
- Receitas/despesas: criação de títulos simples ou parcelados, busca, filtro, paginação e consulta de parcelas/histórico via `TitleService`.
- Consulta de recibos com download privado e notificações com leitura individual/em lote.
- Painel com contagem de títulos abertos, alertas não lidos, próximas parcelas e saldo realizado de até seis contas.
- `APP_KEY` local anteriormente ausente foi gerada; nenhuma chave existente foi rotacionada.
- `TitleDetail` em `/titulos/{title}`: parcelas com valor, data da baixa e restante quando parcial; histórico no formato `Título/Parcela · Data · Valor da parcela` com vencimento, ajustes e formas; vencimento informativo no formulário de baixa; estornos e ciclos gerados.
- Baixa com UUID bloqueado, até 20 formas, desconto/juros/multa e confirmação explícita.
- Estorno com motivo/data e UUID bloqueado; reenvio do mesmo pedido não cria nova movimentação.
- Edição envia somente alterações, preserva vínculos inativos não alterados e IDs das parcelas; após qualquer baixa, somente metadados.
- Cancelamento exige ausência de baixas ativas; recibos invalidados continuam no histórico.
- Emissão de PDF separada, verificação de recibo existente e mensagens para arquivo ausente/corrompido ou falha de armazenamento.
- Recorrência na criação: mensal/anual; parcelas fixadas em 1 e primeiro vencimento calculado pela emissão + dia/mês (`RecurrenceService::firstDueDate()`).
- Detalhe do título: histórico de baixas em ordem crescente de parcela; nova forma de pagamento vem preenchida com o valor que falta.
- Contatos agora incluem endereço, bairro, cidade, UF, CEP e observações; a lista permite arquivar e restaurar sem exclusão física.
- Página `/preferencias`: tema claro/escuro e antecedência de alertas persistidos em `users`, com validação de 0 a 365 dias.
- Layout aplica o tema persistido a cada página autenticada.
- Painel mostra totais abertos e realizados do mês; estornos no período reduzem o realizado.
- Próximos compromissos mostram o principal restante da parcela, não o valor original.
- Abrir baixa exige previamente conta financeira ativa e forma ativa compatível; a interface informa a ausência antes de criar UUID/pedido.
- Alertas podem ser atualizados em `/notificacoes` pelo proprietário autenticado, além da rotina agendada diária.
- Formulário de baixa mostra total esperado, soma das formas e diferença em tempo real; a regra definitiva continua no `TitleService`.
- Edição sem baixas permite ativar, alterar ou desativar recorrência mensal/anual. A interface não expõe essa alteração após histórico ou em cadeia já gerada.
- `/dashboard-financeira` consolida baixas e estornos por período, categoria, forma, conta e contato, com KPIs, evolução diária e distribuições.
- Gráficos SVG com eixos X/Y em escala redonda, tooltip que acompanha a linha mais alta e `App\Support\ChartAxis` compartilhado.
- Histórico diário de saldo por conta (`accounts` em `FinancialAnalyticsService`), seguindo período, conta, categoria, forma e contato na abertura e na série, com mini cards de seleção visual e escala recalculada no navegador.
- Relatório financeiro imprime com cabeçalho, parâmetros, cards de indicadores, agrupamentos sem rótulos técnicos, subtotais destacados, total geral, cores e linhas zebra.
- Impressão do relatório usa página branca mesmo com tema escuro; identifica MoneyControl, usuário/e-mail solicitante e data/hora de emissão.
- Atalhos de arraste (`<x-swipe-row>` + `resources/js/swipe-row.js`) em `/receitas`, `/despesas` e `/cadastros/*`, com mouse, touchpad e toque; botões equivalentes mantidos.
- `ConfirmsOperations` + `<x-confirm-modal>`: estorno, cancelamento, exclusão, baixa e descarte de pedido exigem confirmação no modal; o método da operação consome a confirmação no servidor.
- `App\Support\Feedback` + `<x-feedback>`: mensagens "… com sucesso!" com ícone por operação, fechamento manual/automático e resumo de erros com ícone.
- `<x-gesture-help>`: botão flutuante de ajuda e tutorial contextual (títulos ou cadastros).
- Exclusão lógica e restauração dos quatro cadastros; categorias/contas/formas com vínculos financeiros só podem ser desativadas.
- Variante `dark:` do Tailwind passa a seguir o tema escolhido pelo usuário (`data-theme`).

A edição mantém número, proprietário, tipo, quantidade e IDs das parcelas.
Com baixas, só permite metadados; mudanças de valores e vínculos são bloqueadas.
Estornos reabrem o principal e recalculam estados. Cancelamento exige que não reste
nenhuma baixa ativa; edição financeira continua bloqueada mesmo após estorno.
Próximo ciclo sem histórico é cancelado no estorno da origem. Se tiver movimentos,
o estorno da origem é bloqueado; não há estorno automático em cascata.
Recorrências legadas sem origem rastreável exigem reconciliação antes do estorno.
Os cadastros padrão são adicionados apenas quando ausentes, sem sobrescrever os existentes
nem criar usuários de demonstração.

## Validação Atual

- SQLite: **368 testes aprovados, 1730 assertions** (24/10/2026), incluindo os novos cenários de saldo filtrado.
- Focal MySQL de analytics: **1 teste / 18 assertions** (24/10/2026), com precisão decimal, abertura filtrada, saldo inicial e estornos.
- Pint (44 arquivos), cache de views, build e `git diff --check` aprovados nesta entrega.
- Testes finais executados com `APP_CONFIG_CACHE` apontando para caminho temporário inexistente: o cache da aplicação causou falhas de ambiente/CSRF na primeira execução. Cache original preservado.
- O teste de replay agora compara HTML decodificado do JSON Livewire para suportar mensagens acentuadas.
- Navegador não reexecutado nesta entrega; as verificações Edge abaixo são históricas.
- Edge headless (23/10/2026, SQLite temporário fora do projeto): 22 verificações de gestos, modais, notificações, tutorial, toque em 375px, rolagem vertical sem disparo e console sem erros, nos temas claro e escuro.
- A suíte completa MySQL não foi reexecutada após a migration de 23/10/2026; somente o focal de analytics foi validado em 24/10.
- MySQL: **158 testes aprovados, 1003 assertions na execução final**.
- Concorrência cobre numeração, baixa idempotente, excesso de principal, edição/baixa, cancelamento/baixa, estorno idempotente e origem/filho recorrente.
- Quantidade de assertions das disputas pode variar conforme qual processo vencer; ambos os resultados válidos são testados.
- Concorrência adicional cobre emissão idempotente de PDF, emissão versus estorno e notificações duplicadas.
- Pint, cache de views e build de produção aprovados; nenhuma dependência ou migration foi alterada nesta etapa.
- Regressão MySQL desta entrega executou 157/158 testes: a observação concorrente de dois `LOCK WAIT` falhou uma vez por agendamento de processos; o mesmo cenário isolado passou (1 teste, 11 assertions), sem alteração da asserção estrita.
- Nesta retomada, as suites MySQL focais de títulos e notificações passaram: **70 testes / 389 assertions**.
- Nesta etapa, `MySqlTitleServiceTest` passou com **52 testes / 215 assertions**.
- `FinancialDashboardTest`: **18 testes / 125 assertions**, incluindo abertura e movimentos filtrados, estornos, datas inclusivas, contatos múltiplos e filtros combinados.
- Edge headless (23/10/2026): dashboard completa conferida nos temas claro/escuro e em 375px, com SQLite temporário fora do projeto. `MySqlFinancialAnalyticsTest` não foi reexecutado nesta etapa.
- Edge headless: hover e teclado do gráfico, fundo branco, ancestral visível, cabeçalho e tabela de impressão conferidos; PDF temporário gerado fora do projeto.
- `MySqlFinancialAnalyticsTest` valida precisão decimal, estorno e filtro de conta no banco de testes.
- `schedule:list` reconhece vencidos às 00:05 e alertas às 08:00; scheduler ainda precisa ser iniciado/configurado.
- Migration `2026_10_16_000002_add_receipt_snapshots_and_notification_deduplication` aplicada: 17 migrations, 21 tabelas, 22 FKs restritivas.
- Testes MySQL comuns usam rollback. Testes de concorrência precisam de fixtures confirmadas por commit, removidas ao final apenas pelo ID/email exclusivo do teste.
- Testes de PDF no MySQL também precisam de emissão sem transação externa: confirmam fixtures e removem apenas registros de `artifacts-...@example.test`.
- PDF de teste e cache do renderizador ficam em diretório temporário, não no disco privado da aplicação.
- Testes MySQL nunca fazem reset, truncate ou limpeza global; lifecycle e migrations por teste são restritos ao SQLite em memória.
- Consulta final confirmou zero usuários, títulos, baixas e estornos nos dois bancos, sem fixtures de concorrência remanescentes.
- Conferência desta etapa: zero usuários, títulos, recibos e alertas nos dois bancos; sequência de recibos da aplicação permanece 0.
- Não foram executados seeders, comandos financeiros de escrita ou resets no banco da aplicação.
- Navegador: registro, onboarding, conta com saldo, receita parcelada, detalhes, painel, tema e logout conferidos usando SQLite temporário fora do projeto.
- Testes novos em `WorkspaceTest`: autenticação, recuperação, rollback de onboarding, isolamento, validação monetária, bloqueio de propriedades Livewire e renderização das telas.
- `TitleDetailTest`: 27 testes de interface, incluindo replay de snapshot antigo, colisão de payload/UUID, principal restante, ajustes, estorno, PDF real e falhas, edição/baixa, recorrência, isolamento, pré-requisitos de conta/forma ativa, prévia de total/formas e edição segura de recorrência.
- Navegador desta etapa: baixa parcial de R$ 40,00 sobre R$ 100,00, reenvio sem duplicação, emissão de PDF, estorno com invalidação, edição de metadados e cancelamento após estorno. Banco e PDFs temporários fora do projeto.
- Esta retomada confirmou baixa total/parcial e alertas de vencimento/atraso com dados isolados; atualização manual cria alertas deduplicados sem depender de `schedule:work`.
- Testes PDF usam `DatabaseMigrations` somente em SQLite em memória, sem transação externa à emissão. Autorrelacionamentos temporários são desvinculados no teardown para permitir rollback do schema SQLite; MySQL não recebe essa limpeza.
- A primeira regressão MySQL apresentou uma falha na espera de lock da numeração; a execução isolada e a repetição completa passaram. Mantida a asserção estrita de concorrência; observar estabilidade em execuções sob carga.

## Próxima Etapa

Retomar pelo **refinamento das operações disponíveis, responsividade e cobertura de
integração**, mantendo as preferências e indicadores já entregues.

1. Reexecutar a suíte completa MySQL em `gestao_financeira_testing`, isolando o cache de configuração; o focal de analytics já passou.
2. Preservar rascunhos e reconciliar pedidos após recarregar a página, sem enfraquecer UUID/hash de idempotência.
3. Validar gestos e modais com leitor de tela e em dispositivos reais; avaliar tradução integral das mensagens nativas de validação Laravel.
4. Validar viewport mobile/acessibilidade da dashboard e das operações financeiras, incluindo relatório longo e impressão.
5. Ampliar testes HTTP/Livewire equivalentes no MySQL, incluindo combinações de filtros da dashboard.
6. Implementar importação/exportação e alertas externos após consolidar os módulos.
7. Configurar SMTP, fuso, scheduler e Redis; atualizar PHP para 8.3+ sem alterar dados.
8. Ampliar carga, reconciliação de legados e recuperação explícita de PDFs ausentes/corrompidos/órfãos.

Não refazer saldo, cadastros, edição, vencidos, seeders, estorno básico ou testes de
concorrência já entregues, nem PDF e alertas internos. O estorno atual é integral por baixa,
não parcial nem em cascata. `generate_receipt=true` na baixa ainda é rejeitado:
emitir depois do commit por `ReceiptService` ou `POST /baixas/{settlement}/recibo`.
`ReceiptService::generate()` recusa transação externa para evitar arquivo sem registro.
Recibos legados sem snapshot/hash e PDFs ausentes/corrompidos não são substituídos silenciosamente.
`TitleDetail` mantém UUID/payload após confirmar o pedido e permite reenvio idempotente.
Abrir outro pedido após sucesso ou fechar explicitamente o atual cria uma nova chave.
Após falha de conexão, conferir o histórico antes de iniciar outro pedido; não há
persistência de rascunho/UUID após recarregar a página. Isso não altera os índices
e hashes de idempotência persistidos no domínio.

## Como Verificar

```powershell
$env:APP_CONFIG_CACHE = "$env:TEMP/moneycontrol-testing-config.php"
php artisan test --compact
php vendor/bin/phpunit --configuration phpunit.mysql.xml
# Executar somente concorrencia real (exige leitura de information_schema.innodb_trx)
php vendor/bin/phpunit --configuration phpunit.mysql.xml --filter=MySqlConcurrencyTest
php artisan schedule:list
php artisan migrate:status
npm run build
# Abrir http://127.0.0.1:8000, criar conta e acessar os modulos
php artisan serve
```

O caminho de `APP_CONFIG_CACHE` deve ser inexistente. Remova essa variável do terminal
após os testes (`Remove-Item Env:APP_CONFIG_CACHE`) antes de iniciar a aplicação.
Leia `DEVELOPER_GUIDE.md` para os contratos dos Services e preparação do banco de testes.
O histórico completo está em `AI_DEV_LOG.md`.

## Pendências Técnicas

- Testes de carga prolongada e rotinas de reconciliação continuam pendentes.
- Atualizar PHP para 8.3+; Livewire/MaryUI em produção já alinhados.
- Configurar Redis quando estiver disponível.
- O runtime atual segue em PHP 8.2.0. Tailwind 4 substitui o alvo antigo Tailwind 3 por compatibilidade com MaryUI 2.9/daisyUI 5.
- O scheduler usa atualmente UTC, conforme `config/app.php`; definir o fuso antes da implantação.
- `finance:initialize <id>` exige usuário existente; `db:seed` completa cadastros dos usuários existentes.
- Não rodar seeders no banco de integração MySQL; seus testes assumem domínio vazio.
- A suite de concorrência confirma fixtures temporariamente no banco de testes; se o processo for encerrado abruptamente, revisar somente as fixtures `concurrency-...@example.test` remanescentes.
- Aplicar o mesmo cuidado a fixtures `artifacts-...@example.test`; sequência global do banco de testes pode avançar e não é resetada.
- Hóspedes HTML são direcionados a `/entrar`; JSON recebe 401. Usuários autenticados em rotas de login/registro vão para `/painel`.
- Recuperação de senha funciona via Password Broker; mailer local `log` não entrega e-mails externos. Configurar SMTP antes de disponibilizar esse recurso aos usuários.
- Baixa/estorno/edição/cancelamento e emissão de PDF já estão disponíveis na tela de detalhe. Recorrência pode ser configurada na criação; edição dessa configuração pela UI ainda pendente.
- A baixa atual aceita data futura, conforme contrato existente do Service; PDF só pode ser emitido quando essa data chegar. Estorno não aceita data futura.
- Tema e antecedência são persistidos; a alteração de tema é aplicada nas próximas páginas abertas. Validação dedicada em viewport mobile continua pendente.
- Alertas não são enviados para fora do banco; notificações antigas continuam como histórico após pagamento/cancelamento.
- Não executar `migrate:fresh` ou `migrate:reset` em bancos com dados.
- Reverter a migration `2026_10_23_000001` remove `deleted_at`: cadastros excluídos logicamente voltariam a aparecer.
- Textos próprios da interface revisados em português brasileiro; tradução integral das mensagens nativas Laravel e revisão de conteúdo digitado pelos usuários não fazem parte desta entrega.
