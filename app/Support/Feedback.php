<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Padrao unico de mensagens ao usuario: cada tipo tem icone (MaryUI/Heroicons), tom de cor e
 * duracao. Confirmacoes de operacoes sensiveis tambem sao definidas aqui para manter textos,
 * icones e botoes iguais em todas as telas.
 */
final class Feedback
{
    public const KINDS = [
        'created' => ['icon' => 'o-plus-circle', 'tone' => 'success'],
        'updated' => ['icon' => 'o-pencil-square', 'tone' => 'success'],
        'deleted' => ['icon' => 'o-trash', 'tone' => 'success'],
        'settled' => ['icon' => 'o-banknotes', 'tone' => 'success'],
        'reversed' => ['icon' => 'o-arrow-uturn-left', 'tone' => 'success'],
        'cancelled' => ['icon' => 'o-no-symbol', 'tone' => 'success'],
        'restored' => ['icon' => 'o-arrow-path', 'tone' => 'success'],
        'activated' => ['icon' => 'o-play-circle', 'tone' => 'success'],
        'deactivated' => ['icon' => 'o-pause-circle', 'tone' => 'success'],
        'receipt' => ['icon' => 'o-document-check', 'tone' => 'success'],
        'success' => ['icon' => 'o-check-circle', 'tone' => 'success'],
        'info' => ['icon' => 'o-information-circle', 'tone' => 'info'],
        'warning' => ['icon' => 'o-exclamation-triangle', 'tone' => 'warning'],
        'error' => ['icon' => 'o-x-circle', 'tone' => 'error'],
    ];

    /** Sucesso/informacao fecham sozinhos; aviso e erro exigem fechamento manual. */
    private const TIMEOUTS = ['success' => 6000, 'info' => 8000, 'warning' => 0, 'error' => 0];

    public const CONFIRMATIONS = [
        'reverse' => [
            'title' => 'Estornar baixa?',
            'message' => 'Deseja realmente estornar a baixa deste título? Essa ação poderá alterar a situação financeira do registro.',
            'icon' => 'o-arrow-uturn-left',
            'tone' => 'warning',
            'confirm' => 'Confirmar estorno',
        ],
        'cancel' => [
            'title' => 'Confirmar cancelamento?',
            'message' => 'Deseja realmente cancelar este título? Verifique as informações antes de prosseguir.',
            'icon' => 'o-no-symbol',
            'tone' => 'error',
            'confirm' => 'Confirmar cancelamento',
        ],
        'delete' => [
            'title' => 'Confirmar exclusão?',
            'message' => 'Deseja realmente excluir este registro? Essa ação poderá afetar informações relacionadas, conforme as regras do sistema.',
            'icon' => 'o-trash',
            'tone' => 'error',
            'confirm' => 'Confirmar exclusão',
        ],
        'settle' => [
            'title' => 'Confirmar baixa?',
            'message' => 'Deseja registrar esta movimentação financeira? Confira principal, ajustes, contas e formas antes de prosseguir.',
            'icon' => 'o-banknotes',
            'tone' => 'success',
            'confirm' => 'Confirmar baixa',
        ],
        'discard-payment' => [
            'title' => 'Descartar pedido de baixa?',
            'message' => 'O formulário será fechado sem gravar nada. Se houve falha de conexão, confira o histórico de baixas antes de iniciar outro pedido.',
            'icon' => 'o-exclamation-triangle',
            'tone' => 'warning',
            'confirm' => 'Descartar pedido',
        ],
        'discard-reversal' => [
            'title' => 'Descartar pedido de estorno?',
            'message' => 'O formulário será fechado sem gravar nada. Se houve falha de conexão, confira o histórico antes de iniciar outro estorno.',
            'icon' => 'o-exclamation-triangle',
            'tone' => 'warning',
            'confirm' => 'Descartar pedido',
        ],
    ];

    /**
     * Mensagem para a resposta atual (acoes Livewire renderizam na mesma requisicao).
     */
    public static function now(string $kind, string $message, ?string $detail = null): void
    {
        session()->now('feedback', self::make($kind, $message, $detail));
    }

    /**
     * Mensagem para a proxima requisicao (redirecionamentos de controllers).
     */
    public static function flash(string $kind, string $message, ?string $detail = null): void
    {
        session()->flash('feedback', self::make($kind, $message, $detail));
    }

    /**
     * @return array{id: string, kind: string, message: string, detail: ?string}
     */
    public static function make(string $kind, string $message, ?string $detail = null): array
    {
        self::kind($kind);

        return ['id' => bin2hex(random_bytes(6)), 'kind' => $kind, 'message' => $message, 'detail' => $detail];
    }

    /**
     * @return array{icon: string, tone: string, timeout: int, role: string}
     */
    public static function kind(string $kind): array
    {
        $meta = self::KINDS[$kind] ?? throw new InvalidArgumentException("Tipo de mensagem desconhecido: {$kind}.");

        return $meta + [
            'timeout' => self::TIMEOUTS[$meta['tone']],
            'role' => in_array($meta['tone'], ['error', 'warning'], true) ? 'alert' : 'status',
        ];
    }

    /**
     * @return array{title: string, message: string, icon: string, tone: string, confirm: string}
     */
    public static function confirmation(string $operation): array
    {
        return self::CONFIRMATIONS[$operation]
            ?? throw new InvalidArgumentException("Confirmacao desconhecida: {$operation}.");
    }
}
