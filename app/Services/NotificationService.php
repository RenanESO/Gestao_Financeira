<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Scopes\OwnerScope;
use App\Models\Title;
use App\Models\TitleInstallment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class NotificationService
{
    /** Rotina interna: usuario persistido explicito, sem modificar a sessao. */
    public function generateFor(User $usuario, ?string $data = null): int
    {
        $data = Validator::make(['date' => $data ?? now()->toDateString()], [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])->validate()['date'];

        return DB::transaction(function () use ($usuario, $data) {
            $usuario = User::whereKey($usuario->getKey())->lockForUpdate()->firstOrFail();
            Validator::make(['days' => $usuario->notification_days_before], [
                'days' => ['required', 'integer', 'min:0', 'max:365'],
            ])->validate();
            $limite = CarbonImmutable::parse($data)->addDays($usuario->notification_days_before)->toDateString();
            $titulos = Title::withoutGlobalScope(OwnerScope::class)->where('user_id', $usuario->id)
                ->whereIn('status', ['pending', 'overdue', 'partial']);
            $criadas = 0;
            TitleInstallment::withoutGlobalScope(OwnerScope::class)
                ->whereIn('title_id', $titulos->select('titles.id'))
                ->whereIn('status', ['pending', 'overdue'])->whereDate('due_date', '<=', $limite)
                ->with(['title' => fn ($query) => $query->withoutGlobalScope(OwnerScope::class)
                    ->where('user_id', $usuario->id)])
                ->chunkById(100, function ($parcelas) use ($usuario, $data, &$criadas) {
                    foreach ($parcelas as $parcela) {
                        $vencimento = $parcela->due_date->toDateString();
                        $evento = $vencimento < $data ? 'overdue' : 'due-soon';
                        $chave = hash('sha256', $evento.':'.$parcela->id.':'.$vencimento);
                        $existe = Notification::withoutGlobalScope(OwnerScope::class)
                            ->where('user_id', $usuario->id)->where('deduplication_key', $chave)->exists();
                        if ($existe) {
                            continue;
                        }
                        $notificacao = new Notification([
                            'title' => $evento === 'overdue' ? 'Parcela vencida' : 'Parcela vencendo em breve',
                            'message' => sprintf('%s, parcela %d: %s em %s.',
                                $parcela->title->title_number, $parcela->installment_number,
                                $evento === 'overdue' ? 'venceu' : 'vence', $parcela->due_date->format('d/m/Y')),
                            'type' => $vencimento <= $data ? 'danger' : 'warning',
                        ]);
                        $notificacao->deduplication_key = $chave;
                        $notificacao->user()->associate($usuario);
                        $notificacao->notifiable()->associate($parcela);
                        $notificacao->save();
                        $criadas++;
                    }
                });

            return $criadas;
        }, 3);
    }

    public function markRead(Notification $notificacao): Notification
    {
        $this->requireIdentity();

        return DB::transaction(function () use ($notificacao) {
            User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            $notificacao = Notification::whereKey($notificacao->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $notificacao);
            if ($notificacao->read_at === null) {
                $notificacao->read_at = now();
                $notificacao->save();
            }

            return $notificacao;
        }, 3);
    }

    public function markAllRead(): int
    {
        $this->requireIdentity();

        return DB::transaction(function () {
            $usuario = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();

            return Notification::where('user_id', $usuario->id)->unread()->update(['read_at' => now()]);
        }, 3);
    }

    private function requireIdentity(): void
    {
        if (Auth::id() === null) {
            throw new AuthenticationException;
        }
    }
}
