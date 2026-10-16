<x-layout title="Notificacoes">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
        <h1 class="page-title">Notificacoes</h1>
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline btn-sm">Marcar todas como lidas</button></form>
    </div>
    <p class="muted mb-7">Alertas internos de vencimento. O historico permanece apos leitura ou pagamento.</p>
    <x-feedback />
    <section class="panel">
        @forelse($notifications as $notification)
            <article class="flex flex-wrap items-start justify-between gap-4 border-b border-base-content/10 py-5 last:border-0">
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold">{{ $notification->title }} @if(!$notification->read_at)<span class="badge badge-primary badge-sm ml-2">Nova</span>@endif</h2>
                    <p class="mt-2 text-sm text-base-content/70">{{ $notification->message }}</p>
                    <p class="muted mt-2">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                </div>
                @if(!$notification->read_at)
                    <form method="POST" action="{{ route('notifications.read-web', $notification) }}">@csrf<button class="btn btn-ghost btn-sm">Marcar como lida</button></form>
                @else<span class="muted">Lida</span>@endif
            </article>
        @empty<p class="py-8 text-center muted">Nenhuma notificacao por aqui.</p>@endforelse
        <div class="mt-5">{{ $notifications->links() }}</div>
    </section>
</x-layout>
