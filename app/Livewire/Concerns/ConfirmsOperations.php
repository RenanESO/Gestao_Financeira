<?php

namespace App\Livewire\Concerns;

use App\Support\Feedback;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Confirmacao explicita, em modal, antes de operacoes sensiveis. O pedido pendente fica em uma
 * propriedade bloqueada: botoes e gestos apenas abrem o modal, e o metodo da operacao consome
 * a confirmacao no servidor antes de chamar o Service, que revalida estado e permissoes.
 */
trait ConfirmsOperations
{
    /** @var array{operation: string, target: ?int, subject: ?string}|null */
    #[Locked]
    public ?array $confirmation = null;

    /** Espelho visual do modal; fechar pelo navegador (Esc, X, fundo) descarta o pedido. */
    public bool $confirming = false;

    /**
     * Operacao => metodo publico que executa e consome a confirmacao.
     *
     * @return array<string, string>
     */
    abstract protected function confirmableOperations(): array;

    protected function askConfirmation(string $operation, ?int $target = null, ?string $subject = null): void
    {
        Feedback::confirmation($operation);
        $this->resetValidation();
        $this->confirmation = ['operation' => $operation, 'target' => $target, 'subject' => $subject];
        $this->confirming = true;
    }

    public function updatedConfirming(mixed $value): void
    {
        if (! $value) {
            $this->dismissConfirmation();
        }
    }

    public function dismissConfirmation(): void
    {
        $this->confirmation = null;
        $this->confirming = false;
    }

    public function confirmOperation(): void
    {
        $method = $this->confirmableOperations()[$this->confirmation['operation'] ?? ''] ?? null;
        // Clique repetido apos a conclusao nao encontra pedido pendente e nao executa nada.
        if ($method === null) {
            $this->dismissConfirmation();

            return;
        }
        $target = $this->confirmation['target'];
        try {
            app()->call([$this, $method], $target === null ? [] : ['id' => $target]);
        } catch (ValidationException $exception) {
            // A falha aparece junto ao usuario mesmo que a linha esteja longe do resumo de erros.
            $this->dismissConfirmation();
            Feedback::now('error', 'Não foi possível concluir a operação.', implode(' ', Arr::flatten($exception->errors())));

            throw $exception;
        }
    }

    protected function consumeConfirmation(string $operation, ?int $target = null): void
    {
        if (($this->confirmation['operation'] ?? null) !== $operation || ($this->confirmation['target'] ?? null) !== $target) {
            throw ValidationException::withMessages([
                'confirmation' => 'Confirme a operação na janela de confirmação antes de prosseguir.',
            ]);
        }
        $this->dismissConfirmation();
    }
}
