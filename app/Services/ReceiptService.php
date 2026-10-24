<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\TitleSettlement;
use App\Models\User;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class ReceiptService
{
    public function generate(TitleSettlement $baixa): Receipt
    {
        $this->requireIdentity();
        // Um rollback externo nao pode deixar um PDF sem registro. Emissao e independente da baixa.
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Emita o recibo depois de confirmar a transacao financeira.');
        }
        $caminhoNovo = null;
        try {
            return DB::transaction(function () use ($baixa, &$caminhoNovo) {
                $usuario = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
                $baixa = TitleSettlement::whereKey($baixa->getKey())->lockForUpdate()->firstOrFail();
                Gate::authorize('view', $baixa);
                if ($baixa->reversal()->lockForUpdate()->first() !== null) {
                    $this->invalid('Baixa estornada não permite emitir recibo.');
                }
                if ($baixa->settlement_date->toDateString() > now()->toDateString()) {
                    $this->invalid('Aguarde a data da baixa para emitir recibo.');
                }
                $existente = Receipt::where('settlement_id', $baixa->id)->lockForUpdate()->first();
                if ($existente !== null) {
                    $this->readVerifiedPdf($existente);

                    return $existente;
                }
                if (DB::table('receipts')->where('settlement_id', $baixa->id)->exists()) {
                    $this->invalid('Recibo legado com proprietario inconsistente.');
                }
                $baixa->load(['title.contact', 'installment', 'methods.paymentType', 'methods.financialAccount']);
                $snapshot = $this->snapshot($baixa, $usuario);
                $sequencia = DB::table('document_sequences')->where('name', 'receipts')->lockForUpdate()->first();
                if ($sequencia === null || (int) $sequencia->last_number >= 99999999999) {
                    $this->invalid('Sequência de recibos indisponível ou esgotada.');
                }
                $numero = (int) $sequencia->last_number + 1;
                $numeroRecibo = sprintf('RCB-%s-%06d', now()->format('Y'), $numero);
                $snapshot['receipt_number'] = $numeroRecibo;
                $snapshot['issue_date'] = now()->format('d/m/Y');
                $pdf = Pdf::loadView('receipt', ['snapshot' => $snapshot])->setPaper('a4')
                    ->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false,
                        'isJavascriptEnabled' => false, 'tempDir' => sys_get_temp_dir(),
                        'fontCache' => sys_get_temp_dir()])->output();
                if (! str_starts_with($pdf, '%PDF-')) {
                    $this->invalid('Falha ao gerar documento PDF.');
                }
                $caminhoNovo = 'users/'.$usuario->id.'/'.Str::uuid().'.pdf';
                if (! Storage::disk('receipts')->put($caminhoNovo, $pdf)) {
                    $this->invalid('Falha ao armazenar documento PDF.');
                }
                $recibo = new Receipt(['receipt_number' => $numeroRecibo,
                    'issue_date' => now()->toDateString(), 'pdf_path' => $caminhoNovo]);
                $recibo->forceFill(['snapshot' => $snapshot, 'pdf_sha256' => hash('sha256', $pdf)]);
                $recibo->settlement()->associate($baixa);
                $recibo->user()->associate($usuario);
                $recibo->save();
                DB::table('document_sequences')->where('name', 'receipts')->update(['last_number' => $numero]);

                return $recibo->refresh();
            });
        } catch (Throwable $exception) {
            if ($caminhoNovo !== null) {
                try {
                    Storage::disk('receipts')->delete($caminhoNovo);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            throw $exception;
        }
    }

    public function download(Receipt $recibo): Response
    {
        $this->requireIdentity();

        return DB::transaction(function () use ($recibo) {
            User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            $recibo = Receipt::whereKey($recibo->getKey())->lockForUpdate()->firstOrFail();
            Gate::authorize('view', $recibo);
            $pdf = $this->readVerifiedPdf($recibo);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$recibo->receipt_number.'.pdf"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }, 3);
    }

    private function readVerifiedPdf(Receipt $recibo): string
    {
        if ($recibo->voided_at !== null || $recibo->settlement === null || $recibo->settlement->reversal !== null) {
            $this->invalid('Recibo invalidado ou baixa estornada.');
        }
        if (! preg_match('/^users\/'.$recibo->user_id.'\/[a-f0-9-]{36}\.pdf$/D', $recibo->pdf_path)
            || ! preg_match('/^RCB-[0-9]{4}-[0-9]{6,11}$/D', $recibo->receipt_number)
            || $recibo->pdf_sha256 === null || $recibo->snapshot === null) {
            $this->invalid('Recibo legado exige reconciliação de arquivo e integridade.');
        }
        abort_unless(Storage::disk('receipts')->exists($recibo->pdf_path), 404, 'PDF indisponível.');
        $pdf = Storage::disk('receipts')->get($recibo->pdf_path);
        if (! is_string($pdf) || ! hash_equals($recibo->pdf_sha256, hash('sha256', $pdf))) {
            $this->invalid('Integridade do PDF não confere.');
        }

        return $pdf;
    }

    private function snapshot(TitleSettlement $baixa, User $usuario): array
    {
        $titulo = $baixa->title;
        if ($titulo === null || $baixa->installment === null
            || (int) $baixa->installment->title_id !== (int) $titulo->id
            || ($titulo->contact_id !== null && $titulo->contact === null)) {
            $this->invalid('Vínculos históricos da baixa inconsistentes.');
        }
        $soma = 0;
        $formas = [];
        foreach ($baixa->methods as $forma) {
            $valor = Money::cents($forma->value);
            if ($valor <= 0 || $forma->paymentType === null || $forma->financialAccount === null) {
                $this->invalid('Forma de pagamento ou proprietario inconsistente.');
            }
            $soma += $valor;
            Money::decimal($soma);
            $formas[] = ['name' => $forma->paymentType->name, 'account' => $forma->financialAccount->name,
                'value' => $this->formatMoney($forma->value)];
        }
        $principal = Money::cents($baixa->original_value);
        $desconto = Money::cents($baixa->discount);
        $total = $principal - $desconto + Money::cents($baixa->interest) + Money::cents($baixa->fine);
        if ($principal <= 0 || $principal > Money::cents($baixa->installment->value) || $desconto > $principal
            || $total <= 0 || $total !== Money::cents($baixa->total_value) || $soma !== $total) {
            $this->invalid('Valores históricos da baixa inconsistentes.');
        }

        return [
            'owner' => $usuario->name, 'title_number' => $titulo->title_number,
            'title_type' => $titulo->title_type, 'description' => $titulo->description,
            'reference' => $titulo->reference, 'contact' => $titulo->contact?->name,
            'document' => $titulo->contact?->document, 'installment_number' => $baixa->installment->installment_number,
            'settlement_date' => $baixa->settlement_date->format('d/m/Y'),
            'original_value' => $this->formatMoney($baixa->original_value),
            'discount' => $this->formatMoney($baixa->discount), 'interest' => $this->formatMoney($baixa->interest),
            'fine' => $this->formatMoney($baixa->fine), 'total_value' => $this->formatMoney($baixa->total_value),
            'methods' => $formas, 'notes' => $baixa->notes,
        ];
    }

    private function formatMoney(string $valor): string
    {
        [$inteiro, $fracao] = explode('.', Money::decimal(Money::cents($valor)));

        return 'R$ '.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $inteiro).','.$fracao;
    }

    private function requireIdentity(): void
    {
        if (Auth::id() === null) {
            throw new AuthenticationException;
        }
    }

    private function invalid(string $mensagem): never
    {
        throw ValidationException::withMessages(['receipt' => $mensagem]);
    }
}
