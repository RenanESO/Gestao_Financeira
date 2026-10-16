<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Contact;
use App\Models\FinancialAccount;
use App\Models\FinancialModel;
use App\Models\PaymentType;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CatalogService
{
    private const MODELS = [Category::class, Contact::class, FinancialAccount::class, PaymentType::class];

    public function create(string $model, array $dados): FinancialModel
    {
        $this->assertModel($model);
        $this->requireIdentity();
        Gate::authorize('create', $model);
        $dados = $this->validate($model, $dados);

        return DB::transaction(function () use ($model, $dados) {
            $usuario = $this->lockUser();
            $registro = new $model($dados);
            if ($registro instanceof Contact) {
                $registro->code = $this->contactCode($usuario, $registro->type);
            }
            $registro->user()->associate($usuario);
            $registro->save();

            return $registro->refresh();
        }, 3);
    }

    public function update(FinancialModel $registro, array $dados): FinancialModel
    {
        $model = $registro::class;
        $this->assertModel($model);
        $this->requireIdentity();

        return DB::transaction(function () use ($model, $registro, $dados) {
            $this->lockUser();
            $registro = $model::whereKey($registro->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $registro);
            $dados = $this->validate($model, $dados, true);
            if (array_key_exists('type', $dados) && $dados['type'] !== $registro->type && $this->hasHistory($registro)) {
                $this->invalid('type', 'Tipo nao pode mudar depois que o cadastro possui vinculos financeiros.');
            }
            if ($registro instanceof FinancialAccount && isset($dados['initial_balance'])
                && $dados['initial_balance'] !== $registro->initial_balance && $registro->settlementMethods()->exists()) {
                $this->invalid('initial_balance', 'Saldo inicial nao pode mudar depois que a conta possui movimentos.');
            }
            $registro->fill($dados)->save();

            return $registro->refresh();
        }, 3);
    }

    public function archive(Contact $contato): Contact
    {
        $this->requireIdentity();

        return DB::transaction(function () use ($contato) {
            $this->lockUser();
            $contato = Contact::whereKey($contato->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('delete', $contato);
            $contato->delete();

            return $contato;
        }, 3);
    }

    public function restore(Contact $contato): Contact
    {
        $this->requireIdentity();

        return DB::transaction(function () use ($contato) {
            $this->lockUser();
            $contato = Contact::withTrashed()->whereKey($contato->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('restore', $contato);
            $contato->restore();

            return $contato->refresh();
        }, 3);
    }

    private function validate(string $model, array $dados, bool $atualizacao = false): array
    {
        $tipos = match ($model) {
            Category::class => ['income', 'expense', 'both'],
            Contact::class => ['client', 'supplier', 'both'],
            FinancialAccount::class => ['checking', 'savings', 'credit_card', 'cash', 'investment', 'other'],
            PaymentType::class => ['payment', 'receipt', 'both'],
        };
        $presenca = $atualizacao ? 'sometimes' : 'required';
        $regras = [
            'name' => [$presenca, 'required', 'string', 'max:'.($model === Contact::class ? 255 : 100)],
            'type' => [$presenca, 'required', Rule::in($tipos)],
            'active' => ['sometimes', 'boolean'],
        ];
        if ($model === Category::class || $model === FinancialAccount::class) {
            $regras['color'] = ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/D'];
            $regras['icon'] = ['nullable', 'string', 'max:50'];
        }
        if ($model === FinancialAccount::class) {
            $regras['initial_balance'] = ['sometimes', 'required'];
        }
        if ($model === Contact::class) {
            $regras += [
                'document' => ['nullable', 'string', 'max:18'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:255'],
                'neighborhood' => ['nullable', 'string', 'max:100'],
                'city' => ['nullable', 'string', 'max:100'],
                'state' => ['nullable', 'regex:/^[A-Z]{2}$/D'],
                'zip_code' => ['nullable', 'string', 'max:9'],
                'notes' => ['nullable', 'string', 'max:10000'],
            ];
        }
        foreach ($dados as $chave => $valor) {
            if (! array_key_exists($chave, $regras)) {
                $this->invalid((string) $chave, 'Campo nao permitido neste cadastro.');
            }
            if (is_string($valor)) {
                $dados[$chave] = trim($valor);
            }
        }
        $dados = Validator::make($dados, $regras)->validate();
        if (array_key_exists('initial_balance', $dados)) {
            $dados['initial_balance'] = Money::signedDecimal(Money::signedCents($dados['initial_balance'], 'initial_balance'));
        }

        return $dados;
    }

    private function hasHistory(FinancialModel $registro): bool
    {
        if ($registro instanceof PaymentType) {
            return $registro->settlementMethods()->exists();
        }

        return $registro->titles()->withTrashed()->exists()
            || ($registro instanceof FinancialAccount
                && ($registro->installments()->exists() || $registro->settlementMethods()->exists()));
    }

    private function contactCode(User $usuario, string $tipo): string
    {
        $prefixo = match ($tipo) {
            'client' => 'CLI',
            'supplier' => 'FOR',
            'both' => 'CON',
        };
        $maior = 0;
        foreach (Contact::withTrashed()->where('user_id', $usuario->id)->lockForUpdate()->pluck('code') as $codigo) {
            if (preg_match('/^'.$prefixo.'-([0-9]{1,16})$/iD', $codigo, $partes)) {
                $maior = max($maior, (int) $partes[1]);
            }
        }
        if ($maior >= 9999999999999999) {
            $this->invalid('code', 'Limite de numeracao de contatos atingido.');
        }

        return sprintf('%s-%04d', $prefixo, $maior + 1);
    }

    private function assertModel(string $model): void
    {
        if (! in_array($model, self::MODELS, true)) {
            throw new InvalidArgumentException('Model nao suportado pelo CatalogService.');
        }
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

    private function invalid(string $campo, string $mensagem): never
    {
        throw ValidationException::withMessages([$campo => $mensagem]);
    }
}
