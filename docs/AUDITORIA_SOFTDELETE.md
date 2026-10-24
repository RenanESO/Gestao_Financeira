# 🗑️ Auditoria de SoftDelete — MoneyControl

**Data:** 2026-10-23 · **Autor:** Agente de IA (Claude) · **Base:** código e migrations da branch atual
(18 migrations; 21 tabelas, incluindo `migrations`).

> Escopo: verificar, por tabela, a existência de `deleted_at`, o uso do trait
> `Illuminate\Database\Eloquent\SoftDeletes`, exclusões físicas, relacionamentos e impacto
> em relatórios/dashboard. Critério: **não** aplicar SoftDelete indiscriminadamente; cada
> entidade foi avaliada pela finalidade, integridade referencial e necessidade de auditoria.

## 1. Resumo

| Situação | Tabelas |
|---|---|
| ✅ Verificado — SoftDelete correto | `contacts`, `titles` |
| 🔧 Corrigido nesta entrega | `categories`, `financial_accounts`, `payment_types` |
| ⏸️ Dispensado por justificativa técnica | `title_installments`, `title_settlements`, `title_settlement_methods`, `settlement_reversals`, `receipts`, `notifications`, `document_sequences`, `users` e tabelas técnicas do Laravel |
| 📌 Pendente | Nenhuma adequação de schema pendente; ver seção 5 (pontos de atenção) |

Exclusões físicas encontradas no código da aplicação: **nenhuma de dados de negócio**.
O único `delete` físico é a remoção, em `ReceiptService`, do arquivo PDF **recém-gravado**
quando a transação de emissão falha (não é registro de banco). `forceDelete` é negado por
`FinancialRecordPolicy` para todos os Models financeiros. Todas as FKs de domínio usam
`restrictOnDelete`, impedindo exclusão física em cascata.

## 2. Relatório por tabela

| Tabela | Model | `deleted_at` | Trait | Situação encontrada | Adequação | Ação | Justificativa |
|---|---|---|---|---|---|---|---|
| `contacts` | `Contact` | Sim | Sim | Arquivamento já era soft delete; `Title::contact()` usa `withTrashed()` | Não | ✅ Verificado. Interface passou a chamar a operação de "Excluir" (mesmo soft delete) com confirmação e restauração | Histórico financeiro precisa exibir o contato mesmo excluído |
| `titles` | `Title` | Sim | Sim | Relações de parcelas/baixas usam `withTrashed()`; numeração considera excluídos; saldo e análise incluem histórico de títulos excluídos | Não | ✅ Verificado | A operação de negócio é o **cancelamento** (status), não a exclusão; não há exclusão de título na interface |
| `categories` | `Category` | **Não → Sim** | **Não → Sim** | Só havia `active=false`; não existia exclusão | Sim | 🔧 Migration `2026_10_23_000001` + trait; `CatalogService::delete()/restore()`; `Title::category()` com `withTrashed()` | Atender "excluir registro" com exclusão lógica e restaurável |
| `financial_accounts` | `FinancialAccount` | **Não → Sim** | **Não → Sim** | Idem | Sim | 🔧 Idem; `Title`, `TitleInstallment` e `TitleSettlementMethod` com `withTrashed()` na conta | Idem |
| `payment_types` | `PaymentType` | **Não → Sim** | **Não → Sim** | Idem | Sim | 🔧 Idem; `TitleSettlementMethod::paymentType()` com `withTrashed()` | Idem |
| `title_installments` | `TitleInstallment` | Não | Não | Sem exclusão; estado `cancelled` | Não | ⏸️ Dispensado | Parte imutável do título; identidade preservada na edição; cancelamento é por status |
| `title_settlements` | `TitleSettlement` | Não | Não | Sem exclusão; revertida por estorno | Não | ⏸️ Dispensado | Movimento financeiro: a correção é o **estorno auditável** (`settlement_reversals`), nunca a exclusão |
| `title_settlement_methods` | `TitleSettlementMethod` | Não | Não | Sem exclusão | Não | ⏸️ Dispensado | Composição imutável da baixa; base do saldo |
| `settlement_reversals` | `SettlementReversal` | Não | Não | Model protegido de mass assignment; Policy sem update/delete | Não | ⏸️ Dispensado | Registro de auditoria: deve ser permanente |
| `receipts` | `Receipt` | Não | Não | Invalidação por `voided_at`, preservando número e arquivo | Não | ⏸️ Dispensado | `voided_at` já é a estratégia de invalidação; numeração não pode ser reutilizada |
| `notifications` | `Notification` | Não | Não | Histórico preservado; leitura por `read_at`; deduplicação por chave | Não | ⏸️ Dispensado | Não há exclusão de alertas; histórico mantido após pagamento/cancelamento |
| `document_sequences` | — | Não | — | Contador global sob lock | Não | ⏸️ Dispensado | Estrutura técnica; nunca excluída |
| `users` | `User` | Não | Não | Sem exclusão de conta; FKs `restrictOnDelete` em todo o domínio | Não (nesta etapa) | ⏸️ Dispensado | Exclusão de conta não existe; exigiria política de retenção/LGPD própria |
| `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations` | — | Não | — | Tabelas técnicas do framework | Não | ⏸️ Dispensado | Dados transitórios; o próprio Laravel remove fisicamente |

## 3. Regras adotadas para os cadastros

- `CatalogService::delete(FinancialModel)` faz **somente** `delete()` lógico, dentro de transação
  com lock do usuário, após `Gate::authorize('delete')`. `restore()` faz o inverso com
  `Gate::authorize('restore')`. `archive(Contact)` continua existindo e delega a `delete()`.
- **Contatos** podem ser excluídos mesmo com títulos (comportamento já existente).
- **Categorias, contas e formas** com vínculos financeiros (títulos — inclusive excluídos —,
  parcelas ou formas de baixa) **não podem ser excluídas**: o Service recusa com a mensagem
  "Desative-o para preservar o histórico". Assim nenhum histórico passa a apontar para um
  cadastro oculto, e saldos, dashboard e relatórios não mudam.
- Registro excluído não pode ser editado nem desativado/ativado: precisa ser restaurado.
- Consultas Eloquent padrão ignoram excluídos (listas de seleção, painel, opções da
  dashboard). "Mostrar excluídos" (`withTrashed()`) existe na tela de cadastros.
- `FinancialDefaultsService` passou a considerar excluídos (`withTrashed()`): `finance:initialize`
  e `db:seed` **não recriam** um padrão que o usuário excluiu.
- `FinancialRecordPolicy::delete/restore` permite os quatro cadastros ao dono; `forceDelete`
  continua negado.

## 4. Impactos verificados

| Área | Impacto |
|---|---|
| Saldo (`FinancialAccountService`) | Nenhum: conta excluída não tem movimentos (regra acima) |
| Dashboard/relatório (`FinancialAnalyticsService`) | Nenhum nos valores; listas de chips excluem cadastros excluídos, que não possuem movimentos |
| Painel (`WorkspaceController`) | Conta excluída deixa de aparecer na lista de saldos (sem movimentos, só saldo inicial) |
| Criação/edição de títulos | `TitleService` resolve vínculos com Eloquent: cadastro excluído não pode ser vinculado (404) |
| Baixas | Conta/forma excluída não é encontrada para nova baixa (404); histórico não é afetado |
| Seeders/inicialização | Não recriam padrões excluídos |
| Rollback da migration | Remove a coluna: registros excluídos logicamente **voltam a aparecer**. Não executar em produção sem plano |
| Integrações/importação/tarefas agendadas | Não existem importações; `titles:update-overdue` e `notifications:send-due-soon` não leem esses cadastros |

## 5. Pontos de atenção (registrados, sem adequação nesta entrega)

1. Exclusão de conta de usuário (`users`) não existe; se for criada, definir retenção/anonimização.
2. `titles` tem soft delete, mas a interface não exclui títulos; se for criada, avaliar efeito
   nos ciclos recorrentes e no saldo (hoje o histórico de títulos excluídos permanece no saldo).
3. Banco MySQL de testes (`gestao_financeira_testing`) não existe nesta máquina; a suíte MySQL
   não foi reexecutada com a nova coluna.

## 6. Verificação

- `InteractionShortcutsTest`: exclusão/restauração lógica, bloqueio por vínculos (categoria,
  conta e forma), contato excluído preservado no histórico, cadastro excluído fora das seleções
  e não vinculável, inicialização sem recriar padrões, Policies.
- `MigrationLifecycleTest`: aplica, verifica `deleted_at` nas três tabelas, reverte e reaplica.
- MySQL local `gestao_financeira`: migration aplicada (coluna adicionada, nenhum dado alterado).
