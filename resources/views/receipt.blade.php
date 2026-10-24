<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $snapshot['receipt_number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 20px; }
        .header { border-bottom: 2px solid #444; padding-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; margin: 16px 0; }
        td, th { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #eee; }
        .amount { text-align: right; white-space: nowrap; }
        .footer { margin-top: 24px; color: #666; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RECIBO DE {{ $snapshot['title_type'] === 'receivable' ? 'RECEBIMENTO' : 'PAGAMENTO' }}</h1>
        <p>{{ $snapshot['receipt_number'] }} | Emissão: {{ $snapshot['issue_date'] }}</p>
        <p>Responsavel: {{ $snapshot['owner'] }}</p>
    </div>
    <table>
        <tr><th>Título / parcela</th><td>{{ $snapshot['title_number'] }} / {{ $snapshot['installment_number'] }}</td></tr>
        <tr><th>Descrição</th><td>{{ $snapshot['description'] }}</td></tr>
        <tr><th>Referência</th><td>{{ $snapshot['reference'] }}</td></tr>
        <tr><th>Cliente / fornecedor</th><td>{{ $snapshot['contact'] }} {{ $snapshot['document'] }}</td></tr>
        <tr><th>Data da baixa</th><td>{{ $snapshot['settlement_date'] }}</td></tr>
        <tr><th>Principal desta baixa</th><td>{{ $snapshot['original_value'] }}</td></tr>
        <tr><th>Desconto</th><td>{{ $snapshot['discount'] }}</td></tr>
        <tr><th>Juros</th><td>{{ $snapshot['interest'] }}</td></tr>
        <tr><th>Multa</th><td>{{ $snapshot['fine'] }}</td></tr>
        <tr><th>Total movimentado</th><td><strong>{{ $snapshot['total_value'] }}</strong></td></tr>
    </table>
    <h2>Formas de pagamento / recebimento</h2>
    <table>
        <tr><th>Forma</th><th>Conta</th><th class="amount">Valor</th></tr>
        @foreach ($snapshot['methods'] as $method)
            <tr><td>{{ $method['name'] }}</td><td>{{ $method['account'] }}</td><td class="amount">{{ $method['value'] }}</td></tr>
        @endforeach
    </table>
    <p>{{ $snapshot['notes'] }}</p>
    <p class="footer">Comprovante interno do lançamento financeiro. Não substitui documento fiscal.</p>
</body>
</html>
