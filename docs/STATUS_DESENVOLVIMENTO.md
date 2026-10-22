# Status do Desenvolvimento

**Data:** 22/10/2026

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

Já implementado:

- MySQL 8.0+ com 17 migrations aplicadas nos dois bancos locais.
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
- `TitleDetail` em `/titulos/{title}`: principal original/restante, histórico de formas/ajustes, estornos e ciclos gerados.
- Baixa com UUID bloqueado, até 20 formas, desconto/juros/multa e confirmação explícita.
- Estorno com motivo/data e UUID bloqueado; reenvio do mesmo pedido não cria nova movimentação.
- Edição envia somente alterações, preserva vínculos inativos não alterados e IDs das parcelas; após qualquer baixa, somente metadados.
- Cancelamento exige ausência de baixas ativas; recibos invalidados continuam no histórico.
- Emissão de PDF separada, verificação de recibo existente e mensagens para arquivo ausente/corrompido ou falha de armazenamento.
- Recorrência na criação: mensal/anual, uma parcela, dia/mes compatíveis com o vencimento.
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
- Relatório financeiro imprime com cabeçalho, parâmetros, cards de indicadores, agrupamentos sem rótulos técnicos, subtotais destacados, total geral, cores e linhas zebra.
- Impressão do relatório usa página branca mesmo com tema escuro; identifica MoneyControl, usuário/e-mail solicitante e data/hora de emissão.

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

- SQLite: **324 testes aprovados, 1446 assertions**.
- MySQL: **158 testes aprovados, 1003 assertions na execução final**.
- Concorrência cobre numeração, baixa idempotente, excesso de principal, edição/baixa, cancelamento/baixa, estorno idempotente e origem/filho recorrente.
- Quantidade de assertions das disputas pode variar conforme qual processo vencer; ambos os resultados válidos são testados.
- Concorrência adicional cobre emissão idempotente de PDF, emissão versus estorno e notificações duplicadas.
- Pint, cache de views e build de produção aprovados; nenhuma dependência ou migration foi alterada nesta etapa.
- Regressão MySQL desta entrega executou 157/158 testes: a observação concorrente de dois `LOCK WAIT` falhou uma vez por agendamento de processos; o mesmo cenário isolado passou (1 teste, 11 assertions), sem alteração da asserção estrita.
- Nesta retomada, as suites MySQL focais de títulos e notificações passaram: **70 testes / 389 assertions**.
- Nesta etapa, `MySqlTitleServiceTest` passou com **52 testes / 215 assertions**.
- `FinancialDashboardTest`: **13 testes / 84 assertions**, incluindo datas com eventos, líquido zero e intervalo de um dia.
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

1. Preservar rascunhos e reconciliar pedidos após recarregar a página, sem enfraquecer UUID/hash de idempotência.
2. Validar viewport mobile/acessibilidade da dashboard e das operações financeiras, incluindo relatório longo e impressão.
3. Ampliar testes HTTP/Livewire equivalentes no MySQL, incluindo combinações de filtros da dashboard.
4. Implementar importação/exportação e alertas externos após consolidar os módulos.
5. Configurar SMTP, fuso, scheduler e Redis; atualizar PHP para 8.3+ sem alterar dados.
6. Ampliar carga, reconciliação de legados e recuperação explícita de PDFs ausentes/corrompidos/órfãos.

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
