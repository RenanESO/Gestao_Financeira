<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\PaymentType;
use App\Models\SettlementReversal;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\TitleSettlement;
use App\Models\TitleSettlementMethod;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TitleService
{
    public function __construct(private readonly RecurrenceService $recurrence) {}

    /**
     * Cria titulo do autenticado e parcelas cuja soma corresponde exatamente ao principal.
     *
     * @param  array<string, mixed>  $dados
     */
    public function create(array $dados): Title
    {
        $this->requireIdentity();
        Gate::authorize('create', Title::class);
        $dados = $this->validateCreate($dados);

        return DB::transaction(fn () => $this->createRecord($this->lockUser(), $dados), 3);
    }

    /**
     * Mantem identidade, tipo, quantidade e IDs de parcelas. Apos baixas, apenas metadados.
     */
    public function update(Title $titulo, array $dados): Title
    {
        $this->requireIdentity();
        $metadados = ['description', 'document_number', 'reference', 'notes'];
        $financeiros = [
            'original_value', 'issue_date', 'due_date', 'contact_id', 'category_id',
            'financial_account_id', 'is_recurring', 'recurrence_type', 'recurrence_day', 'recurrence_month',
        ];
        foreach (array_keys($dados) as $campo) {
            if (! in_array($campo, [...$metadados, ...$financeiros], true)) {
                $this->invalid((string) $campo, 'Campo não permitido na edição do título.');
            }
        }

        return DB::transaction(function () use ($titulo, $dados, $metadados, $financeiros) {
            $usuario = $this->lockUser();
            $titulo = Title::whereKey($titulo->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $titulo);
            if ($titulo->status === 'cancelled') {
                $this->invalid('status', 'Título cancelado não pode ser editado.');
            }
            $alteraFinanceiro = count(array_intersect(array_keys($dados), $financeiros)) > 0;
            if (! $alteraFinanceiro) {
                $dados = Validator::make($dados, [
                    'description' => ['sometimes', 'required', 'string', 'max:255'],
                    'document_number' => ['nullable', 'string', 'max:50'],
                    'reference' => ['nullable', 'string', 'max:255'],
                    'notes' => ['nullable', 'string', 'max:10000'],
                ])->validate();
                $titulo->fill($dados)->save();

                return $titulo->load('installments');
            }
            if ($titulo->settlements()->lockForUpdate()->first() !== null
                || ! in_array($titulo->status, ['pending', 'overdue'], true)) {
                $this->invalid('status', 'Após baixas, apenas descrição, documento, referencia e observações podem mudar.');
            }
            $camposRecorrencia = ['is_recurring', 'recurrence_type', 'recurrence_day', 'recurrence_month'];
            if (count(array_intersect(array_keys($dados), $camposRecorrencia)) > 0
                && ($titulo->parent_title_id !== null || $titulo->children()->withTrashed()->lockForUpdate()->first() !== null)) {
                $this->invalid('recurrence_type', 'Recorrência não pode mudar em uma cadeia de ciclos já criada.');
            }
            $anteriores = $titulo->only([...$metadados, ...$financeiros, 'title_type', 'installments_count']);
            $anteriores['issue_date'] = $titulo->issue_date->toDateString();
            $anteriores['due_date'] = $titulo->due_date->toDateString();
            $normalizados = $this->validateCreate(array_replace($anteriores, $dados));
            $parcelas = $titulo->installments()->orderBy('installment_number')->lockForUpdate()->get();
            if ($parcelas->count() !== $titulo->installments_count) {
                $this->invalid('installments', 'Parcelamento inconsistente.');
            }
            foreach ($parcelas as $indice => $parcela) {
                if ($parcela->installment_number !== $indice + 1
                    || ! in_array($parcela->status, ['pending', 'overdue'], true)
                    || $parcela->settlements()->lockForUpdate()->first() !== null) {
                    $this->invalid('installments', 'Parcela inconsistente ou com histórico de baixa.');
                }
            }
            $links = array_intersect_key($normalizados, array_intersect_key($dados,
                array_flip(['contact_id', 'category_id', 'financial_account_id'])));
            $links['title_type'] = $titulo->title_type;
            $this->assignLinks($titulo, $usuario, $links);
            unset($normalizados['contact_id'], $normalizados['category_id'], $normalizados['financial_account_id']);
            $titulo->fill($normalizados);

            $centavos = Money::cents($titulo->original_value);
            $quantidade = $titulo->installments_count;
            $vencimento = CarbonImmutable::parse($titulo->due_date);
            foreach ($parcelas as $indice => $parcela) {
                $parcela->value = Money::decimal(intdiv($centavos, $quantidade) + ($indice < $centavos % $quantidade ? 1 : 0));
                $parcela->due_date = $vencimento->addMonthsNoOverflow($indice)->toDateString();
                $parcela->financial_account_id = $titulo->financial_account_id;
                $parcela->status = $parcela->due_date->toDateString() < now()->toDateString() ? 'overdue' : 'pending';
                $parcela->save();
            }
            $titulo->status = $parcelas->contains('status', 'overdue') ? 'overdue' : 'pending';
            $titulo->save();

            return $titulo->load('installments');
        }, 3);
    }

    /**
     * Baixa principal parcial ou total; idempotency_key UUID e original_value sao obrigatorios.
     *
     * @param  array<string, mixed>  $dados
     */
    public function settle(Title $titulo, TitleInstallment $parcela, array $dados): TitleSettlement
    {
        $this->requireIdentity();
        $pedido = $this->validateSettlement($dados);
        $tituloId = $titulo->getKey();
        $parcelaId = $parcela->getKey();

        return DB::transaction(function () use ($tituloId, $parcelaId, $pedido) {
            // Todas as operacoes seguem usuario -> titulo -> parcelas para serializar saldo e numeracao.
            $usuario = $this->lockUser();
            $titulo = Title::whereKey($tituloId)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $titulo);
            $parcela = TitleInstallment::whereKey($parcelaId)->where('title_id', $titulo->id)
                ->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', json_encode([$parcela->id, $pedido], JSON_THROW_ON_ERROR));
            $existente = TitleSettlement::where('title_id', $titulo->id)
                ->where('idempotency_key', $pedido['idempotency_key'])->lockForUpdate()->first();

            if ($existente !== null) {
                if (! hash_equals((string) $existente->request_hash, $hash)) {
                    $this->invalid('idempotency_key', 'Esta chave já foi usada para outra solicitação.');
                }

                return $existente->load('methods');
            }

            if (! in_array($titulo->status, ['pending', 'overdue', 'partial'], true)
                || ! in_array($parcela->status, ['pending', 'overdue'], true)) {
                $this->invalid('status', 'Título ou parcela não permite uma nova baixa.');
            }

            $parcelas = $titulo->installments()->lockForUpdate()->get();
            if ($parcelas->count() !== $titulo->installments_count) {
                $this->invalid('installments', 'Parcelamento inconsistente.');
            }
            $somaParcelas = 0;
            foreach ($parcelas as $item) {
                $valor = Money::cents($item->value);
                if ($valor <= 0 || $item->status === 'cancelled') {
                    $this->invalid('installments', 'Parcela inválida ou cancelada.');
                }
                $somaParcelas += $valor;
                Money::decimal($somaParcelas, 'installments');
            }
            if ($somaParcelas !== Money::cents($titulo->original_value)) {
                $this->invalid('installments', 'A soma das parcelas não confere com o título.');
            }

            $principalBaixado = 0;
            foreach ($parcela->settlements()->active()->lockForUpdate()->get() as $baixa) {
                if ((int) $baixa->title_id !== (int) $titulo->id) {
                    $this->invalid('installments', 'Existe baixa vinculada a outro título.');
                }
                $principalBaixado += Money::cents($baixa->original_value);
            }
            $restante = Money::cents($parcela->value) - $principalBaixado;
            if ($pedido['principal'] > $restante) {
                $this->invalid('original_value', 'O principal da baixa excede o saldo da parcela.');
            }
            $ultimoEstorno = SettlementReversal::whereHas('settlement',
                fn ($query) => $query->where('title_installment_id', $parcela->id))
                ->orderByDesc('reversal_date')->lockForUpdate()->first();
            if ($ultimoEstorno !== null && $pedido['settlement_date'] < $ultimoEstorno->reversal_date->toDateString()) {
                $this->invalid('settlement_date', 'Nova baixa não pode anteceder o ultimo estorno da parcela.');
            }

            $metodos = [];
            foreach ($pedido['methods'] as $indice => $metodo) {
                $forma = PaymentType::whereKey($metodo['payment_type_id'])->lockForUpdate()->firstOrFail();
                $conta = FinancialAccount::whereKey($metodo['financial_account_id'])->lockForUpdate()->firstOrFail();
                $tipo = $titulo->title_type === 'receivable' ? 'receipt' : 'payment';
                if (! $forma->active || ! $conta->active || ! in_array($forma->type, [$tipo, 'both'], true)) {
                    $this->invalid("payment_methods.$indice", 'Forma ou conta inativa, ou tipo incompatível.');
                }
                $metodos[] = [$metodo, $forma, $conta];
            }
            if ($pedido['settlement_date'] < $titulo->issue_date->toDateString()) {
                $this->invalid('settlement_date', 'A baixa não pode ocorrer antes da emissão.');
            }

            $baixa = new TitleSettlement([
                'settlement_date' => $pedido['settlement_date'],
                'original_value' => Money::decimal($pedido['principal']),
                'discount' => Money::decimal($pedido['discount']),
                'interest' => Money::decimal($pedido['interest']),
                'fine' => Money::decimal($pedido['fine']),
                'total_value' => Money::decimal($pedido['total']),
                'notes' => $pedido['notes'],
            ]);
            $baixa->forceFill(['idempotency_key' => $pedido['idempotency_key'], 'request_hash' => $hash]);
            $baixa->installment()->associate($parcela);
            $titulo->settlements()->save($baixa);
            foreach ($metodos as [$metodo, $forma, $conta]) {
                $registro = new TitleSettlementMethod(['value' => Money::decimal($metodo['cents'])]);
                $registro->paymentType()->associate($forma);
                $registro->financialAccount()->associate($conta);
                $baixa->methods()->save($registro);
            }

            $parcela->status = $pedido['principal'] === $restante ? 'paid'
                : ($parcela->due_date->toDateString() < now()->toDateString() ? 'overdue' : 'pending');
            $parcela->save();
            $todasPagas = $titulo->installments()->where('status', '!=', 'paid')->lockForUpdate()->first() === null;
            $titulo->status = $todasPagas ? 'paid' : 'partial';
            $titulo->save();

            if ($todasPagas && $titulo->is_recurring) {
                $this->createRecurringRecord($usuario, $titulo, $baixa);
            }

            return $baixa->load('methods');
        }, 3);
    }

    /**
     * Cancela sem apagar historico. Titulos com baixas ativas exigem estorno primeiro.
     */
    public function cancel(Title $titulo): Title
    {
        $this->requireIdentity();
        $tituloId = $titulo->getKey();

        return DB::transaction(function () use ($tituloId) {
            $this->lockUser();
            $titulo = Title::whereKey($tituloId)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $titulo);
            if ($titulo->settlements()->active()->lockForUpdate()->first() !== null || $titulo->status === 'paid') {
                $this->invalid('status', 'Título com baixas não pode ser cancelado sem estorno.');
            }
            $titulo->installments()->update(['status' => 'cancelled']);
            $titulo->status = 'cancelled';
            $titulo->save();

            return $titulo;
        }, 3);
    }

    private function requireIdentity(): void
    {
        if (Auth::id() === null) {
            throw new AuthenticationException;
        }
    }

    private function lockUser(): User
    {
        return User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
    }

    private function validateCreate(array $dados): array
    {
        $dados = Validator::make($dados, [
            'title_type' => ['required', Rule::in(['receivable', 'payable'])],
            'description' => ['required', 'string', 'max:255'],
            'original_value' => ['required'],
            'installments_count' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'issue_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'contact_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'financial_account_id' => ['nullable', 'integer', 'min:1'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_type' => ['nullable', Rule::in(['monthly', 'yearly'])],
            'recurrence_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'recurrence_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'user_id' => ['prohibited'],
            'parent_title_id' => ['prohibited'],
            'title_number' => ['prohibited'],
            'status' => ['prohibited'],
        ])->validate();
        $quantidade = (int) ($dados['installments_count'] ?? 1);
        $principal = Money::cents($dados['original_value'], 'original_value');
        if ($principal < $quantidade) {
            $this->invalid('original_value', 'Cada parcela deve ter pelo menos um centavo.');
        }
        $dados['original_value'] = Money::decimal($principal);
        $dados['installments_count'] = $quantidade;
        $dados['is_recurring'] = (bool) ($dados['is_recurring'] ?? false);
        if ($dados['is_recurring']) {
            if ($quantidade !== 1 || empty($dados['recurrence_type']) || empty($dados['recurrence_day'])) {
                $this->invalid('recurrence_type', 'Recorrência exige tipo, dia e uma única parcela.');
            }
            $vencimento = CarbonImmutable::parse($dados['due_date']);
            if ($vencimento->day !== min((int) $dados['recurrence_day'], $vencimento->daysInMonth)
                || ($dados['recurrence_type'] === 'yearly'
                    && (int) ($dados['recurrence_month'] ?? 0) !== $vencimento->month)) {
                $this->invalid('due_date', 'Vencimento deve corresponder ao dia e mês da recorrência.');
            }
        } elseif (! empty($dados['recurrence_type']) || ! empty($dados['recurrence_day']) || ! empty($dados['recurrence_month'])) {
            $this->invalid('recurrence_type', 'Não informe recorrência para um título não recorrente.');
        }
        if (($dados['recurrence_type'] ?? null) === 'monthly' && ! empty($dados['recurrence_month'])) {
            $this->invalid('recurrence_month', 'Recorrência mensal não utiliza mês fixo.');
        }

        return $dados;
    }

    private function createRecord(User $usuario, array $dados, ?Title $raiz = null, bool $historico = false): Title
    {
        $titulo = new Title;
        $this->assignLinks($titulo, $usuario, $dados, $historico);
        $titulo->fill($dados);
        $titulo->title_number = $this->nextNumber($usuario, $dados['title_type']);
        $titulo->status = 'pending';
        $titulo->current_installment = 1;
        $titulo->parent()->associate($raiz);
        $usuario->titles()->save($titulo);

        $centavos = Money::cents($titulo->original_value);
        $quantidade = $titulo->installments_count;
        $vencimento = CarbonImmutable::parse($titulo->due_date);
        for ($indice = 0; $indice < $quantidade; $indice++) {
            $valor = intdiv($centavos, $quantidade) + ($indice < $centavos % $quantidade ? 1 : 0);
            $parcela = new TitleInstallment([
                'installment_number' => $indice + 1, 'value' => Money::decimal($valor),
                'due_date' => $vencimento->addMonthsNoOverflow($indice)->toDateString(), 'status' => 'pending',
            ]);
            $parcela->financialAccount()->associate($titulo->financialAccount);
            $titulo->installments()->save($parcela);
        }

        return $titulo->load('installments');
    }

    private function assignLinks(Title $titulo, User $usuario, array &$dados, bool $historico = false): void
    {
        $links = [
            'category_id' => [Category::class, 'category', $dados['title_type'] === 'receivable' ? 'income' : 'expense'],
            'contact_id' => [Contact::class, 'contact', $dados['title_type'] === 'receivable' ? 'client' : 'supplier'],
            'financial_account_id' => [FinancialAccount::class, 'financialAccount', null],
        ];
        foreach ($links as $chave => [$model, $relacao, $tipo]) {
            if (array_key_exists($chave, $dados)) {
                $registro = null;
                if ($dados[$chave] !== null) {
                    $consulta = $model::whereKey($dados[$chave])->where('user_id', $usuario->id);
                    if ($historico && $model === Contact::class) {
                        $consulta->withTrashed();
                    }
                    $registro = $consulta->lockForUpdate()->firstOrFail();
                    if (! $historico && (! $registro->active || ($tipo && ! in_array($registro->type, [$tipo, 'both'], true)))) {
                        $this->invalid($chave, 'Cadastro inativo ou tipo incompatível com o título.');
                    }
                }
                $titulo->$relacao()->associate($registro);
            }
            unset($dados[$chave]);
        }
    }

    private function nextNumber(User $usuario, string $tipo): string
    {
        $prefixo = $tipo === 'receivable' ? 'REC' : 'PAG';
        $ultimo = 0;
        // O lock do usuario protege a sequencia ate o commit; soft deletes nao liberam numeros.
        foreach (Title::withTrashed()->where('user_id', $usuario->id)->lockForUpdate()->pluck('title_number') as $numero) {
            if (preg_match('/^'.$prefixo.'-([0-9]{1,16})$/iD', $numero, $partes)) {
                $ultimo = max($ultimo, (int) $partes[1]);
            }
        }
        if ($ultimo >= 9999999999999999) {
            $this->invalid('title_number', 'Limite de numeração atingido.');
        }

        return sprintf('%s-%04d', $prefixo, $ultimo + 1);
    }

    private function validateSettlement(array $dados): array
    {
        $dados = Validator::make($dados, [
            'idempotency_key' => ['required', 'uuid'],
            'settlement_date' => ['required', 'date_format:Y-m-d'],
            'original_value' => ['required'],
            'discount' => ['sometimes', 'required'],
            'interest' => ['sometimes', 'required'],
            'fine' => ['sometimes', 'required'],
            'total_value' => ['sometimes', 'required'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'generate_receipt' => ['sometimes', 'boolean'],
            'payment_methods' => ['required', 'array', 'min:1', 'max:20'],
            'payment_methods.*' => ['array:payment_type_id,financial_account_id,value'],
            'payment_methods.*.payment_type_id' => ['required', 'integer', 'min:1'],
            'payment_methods.*.financial_account_id' => ['required', 'integer', 'min:1'],
            'payment_methods.*.value' => ['required'],
        ])->validate();
        if ($dados['generate_receipt'] ?? false) {
            $this->invalid('generate_receipt', 'Emita o recibo separadamente pelo ReceiptService após confirmar a baixa.');
        }
        $principal = Money::cents($dados['original_value'], 'original_value');
        $desconto = Money::cents($dados['discount'] ?? '0', 'discount');
        $juros = Money::cents($dados['interest'] ?? '0', 'interest');
        $multa = Money::cents($dados['fine'] ?? '0', 'fine');
        $total = $principal - $desconto + $juros + $multa;
        if ($principal <= 0 || $desconto > $principal || $total <= 0) {
            $this->invalid('original_value', 'Principal e total devem ser positivos; desconto não pode exceder o principal.');
        }
        Money::decimal($total, 'total_value');
        if (array_key_exists('total_value', $dados) && Money::cents($dados['total_value'], 'total_value') !== $total) {
            $this->invalid('total_value', 'Total não confere com principal, desconto, juros e multa.');
        }
        $soma = 0;
        $metodos = [];
        foreach (array_values($dados['payment_methods']) as $indice => $metodo) {
            $valor = Money::cents($metodo['value'], "payment_methods.$indice.value");
            if ($valor <= 0) {
                $this->invalid("payment_methods.$indice.value", 'Forma deve ter valor positivo.');
            }
            $soma += $valor;
            $metodos[] = [
                'payment_type_id' => (int) $metodo['payment_type_id'],
                'financial_account_id' => (int) $metodo['financial_account_id'],
                'cents' => $valor,
            ];
        }
        if ($soma !== $total) {
            $this->invalid('payment_methods', 'A soma das formas deve ser exatamente igual ao total.');
        }

        return [
            'idempotency_key' => strtolower($dados['idempotency_key']),
            'settlement_date' => $dados['settlement_date'],
            'principal' => $principal, 'discount' => $desconto, 'interest' => $juros,
            'fine' => $multa, 'total' => $total, 'notes' => $dados['notes'] ?? null, 'methods' => $metodos,
        ];
    }

    private function createRecurringRecord(User $usuario, Title $titulo, TitleSettlement $baixa): void
    {
        $vencimento = $this->recurrence->nextDueDate($titulo);
        $raiz = $titulo->parent_title_id ? Title::withTrashed()->lockForUpdate()->findOrFail($titulo->parent_title_id) : $titulo;
        if ($raiz->parent_title_id !== null) {
            $this->invalid('parent_title_id', 'A recorrência deve apontar diretamente para a raiz.');
        }
        $dados = $titulo->only([
            'title_type', 'description', 'original_value', 'contact_id', 'category_id', 'financial_account_id',
            'document_number', 'notes', 'is_recurring', 'recurrence_type', 'recurrence_day', 'recurrence_month',
        ]);
        $dados['installments_count'] = 1;
        $dados['issue_date'] = min(now()->toDateString(), $vencimento->toDateString());
        $dados['due_date'] = $vencimento->toDateString();
        $dados['reference'] = $vencimento->format('m/Y');
        $proximo = $this->createRecord($usuario, $this->validateCreate($dados), $raiz, true);
        $proximo->generatedBySettlement()->associate($baixa);
        $proximo->save();
    }

    private function invalid(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
