{{--
    Botao flutuante de ajuda + tutorial dos atalhos de arraste, com conteudo por contexto da tela.
    Usa o modal do MaryUI com <dialog> nativo: Esc, botao de fechar e foco controlados pelo navegador.
--}}
@props(['context'])
@php
    $tutorials = [
        'titles' => [
            'subject' => 'títulos',
            'gestures' => [
                ['right', 'Arrastar para a direita', 'Iniciar a baixa do título, quando permitida.', 'o-banknotes', 'success'],
                ['left', 'Arrastar para a esquerda', 'Cancelar o título ou estornar a baixa, conforme a situação.', 'o-arrow-uturn-left', 'warning'],
            ],
            'notes' => [
                'Títulos sem baixa ativa são cancelados; títulos com baixa abrem o estorno da última baixa ativa.',
                'Títulos pagos ou cancelados informam o motivo quando a ação não é permitida.',
            ],
        ],
        'catalog' => [
            'subject' => 'cadastros',
            'gestures' => [
                ['right', 'Arrastar para a direita', 'Editar o registro no formulário ao lado.', 'o-pencil-square', 'info'],
                ['left', 'Arrastar para a esquerda', 'Excluir o registro, mediante confirmação.', 'o-trash', 'error'],
            ],
            'notes' => [
                'A exclusão é lógica: o registro pode ser restaurado em "Mostrar excluídos".',
                'Categorias, contas e formas com vínculos financeiros devem ser desativadas, não excluídas.',
            ],
        ],
    ];
    $tutorial = $tutorials[$context];
    $id = 'gesture-help-'.$context;
@endphp
<div class="print:hidden">
    <div class="h-16" aria-hidden="true"></div>
    <button type="button" class="help-fab" onclick="document.getElementById('{{ $id }}').showModal()"
        aria-haspopup="dialog" aria-controls="{{ $id }}" title="Atalhos desta tela">
        <x-mary-icon name="o-question-mark-circle" class="size-7" aria-hidden="true" />
        <span class="sr-only">Ajuda: atalhos de arraste da tela de {{ $tutorial['subject'] }}</span>
    </button>
    <x-mary-modal :id="$id" box-class="max-w-lg" without-trap-focus aria-labelledby="{{ $id }}-title">
        <div class="flex items-start gap-3">
            <span class="confirm-modal__icon swipe-tone--info" aria-hidden="true"><x-mary-icon name="o-cursor-arrow-rays" class="size-6" /></span>
            <div>
                <h2 id="{{ $id }}-title" class="text-lg font-semibold">Atalhos de arraste</h2>
                <p class="mt-1 text-sm text-base-content/70">Arraste um registro na horizontal e solte depois do limite indicado. Para desistir, volte o registro à posição original antes de soltar ou pressione Esc.</p>
            </div>
        </div>
        <ul class="mt-5 space-y-3" role="list">
            @foreach($tutorial['gestures'] as [$direction, $gesture, $action, $icon, $tone])
                <li class="gesture-tip">
                    <div class="gesture-demo gesture-demo--{{ $direction }}" aria-hidden="true">
                        <span class="gesture-demo__action swipe-tone--{{ $tone }}"><x-mary-icon :name="$icon" class="size-4" /></span>
                        <span class="gesture-demo__row"><span></span><span></span></span>
                    </div>
                    <div class="min-w-0">
                        <p class="flex items-center gap-1.5 font-semibold">
                            <x-mary-icon :name="$direction === 'right' ? 'o-arrow-right' : 'o-arrow-left'" class="size-4 text-primary" aria-hidden="true" />{{ $gesture }}
                        </p>
                        <p class="text-sm text-base-content/70">{{ $action }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
        <ul class="mt-5 space-y-2 text-sm text-base-content/70" role="list">
            @foreach($tutorial['notes'] as $note)
                <li class="flex gap-2"><x-mary-icon name="o-information-circle" class="mt-0.5 size-4 shrink-0 text-info" aria-hidden="true" />{{ $note }}</li>
            @endforeach
            <li class="flex gap-2"><x-mary-icon name="o-shield-check" class="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" />Estorno, cancelamento e exclusão sempre pedem confirmação. Os botões de cada registro continuam disponíveis.</li>
        </ul>
        <form method="dialog" class="modal-action">
            <button class="btn btn-primary" autofocus><x-mary-icon name="o-check" class="size-4" aria-hidden="true" />Entendi</button>
        </form>
    </x-mary-modal>
</div>
