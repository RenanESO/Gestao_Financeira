<x-layout title="Recibos">
    <h1 class="page-title">Recibos</h1>
    <p class="muted mt-3 mb-7">Documentos privados. Recibos invalidados permanecem no histórico.</p>
    <section class="panel">
        <div class="table-wrap"><table class="table"><thead><tr><th>Número</th><th>Emissão</th><th>Status</th><th>Documento</th></tr></thead><tbody>
            @forelse($receipts as $receipt)
                <tr><td>{{ $receipt->receipt_number }}</td><td>{{ $receipt->issue_date->format('d/m/Y') }}</td><td>{{ $receipt->voided_at ? 'Invalidado' : 'Emitido' }}</td><td>
                    @if(!$receipt->voided_at && $receipt->snapshot && $receipt->pdf_sha256)
                        <a href="{{ route('receipts.download', $receipt) }}" class="btn btn-outline btn-sm">Baixar PDF</a>
                    @else
                        <span class="muted">Indisponível</span>
                    @endif
                </td></tr>
            @empty<tr><td colspan="4" class="py-10 text-center muted">Nenhum recibo emitido.</td></tr>@endforelse
        </tbody></table></div>
        <div class="mt-5">{{ $receipts->links() }}</div>
    </section>
</x-layout>
