<section class="financial-report__group financial-report__group--depth-{{ min($level, 2) }}">
    <h2>{{ $group['label'] }}</h2>
    @foreach($group['children'] as $child)
        @include('components.financial-report-group', ['group' => $child, 'level' => $level + 1])
    @endforeach
    @if(!$group['children'])
        <div class="financial-report__table-wrap"><table class="financial-report__table">
            <thead><tr><th>Data / evento</th><th>Titulo / descricao</th><th>Contato / categoria</th><th>Forma</th><th>Entradas</th><th>Saidas</th><th>Saldo</th></tr></thead>
            <tbody>
                @foreach($group['rows'] as $row)
                    <tr>
                        <td><strong>{{ \Carbon\CarbonImmutable::parse($row['date'])->format('d/m/Y') }}</strong><small>{{ $row['event'] }} #{{ $row['settlement_id'] }}</small></td>
                        <td><strong>{{ $row['title_number'] }}</strong><small>{{ $row['description'] }}</small></td>
                        <td><strong>{{ $row['contact'] }}</strong><small>{{ $row['category'] }}</small></td>
                        <td>{{ $row['method'] }}</td>
                        <td class="financial-report__amount financial-report__amount--income">{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($row['income'])) }}</td>
                        <td class="financial-report__amount financial-report__amount--expense">{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($row['expense'])) }}</td>
                        <td class="financial-report__amount financial-report__amount--balance">{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($row['income'] - $row['expense'])) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
    <div class="financial-report__subtotal">
        <span>Subtotal de {{ $group['label'] }}</span>
        <span>Entradas <strong class="financial-report__amount--income">{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($group['totals']['income'])) }}</strong></span>
        <span>Saídas <strong class="financial-report__amount--expense">{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($group['totals']['expense'])) }}</strong></span>
        <span>Saldo <strong class="financial-report__amount--balance">{{ \App\Support\Display::money(\App\Support\Money::signedDecimal($group['totals']['balance'])) }}</strong></span>
    </div>
</section>
