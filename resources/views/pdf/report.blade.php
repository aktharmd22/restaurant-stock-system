<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        /* dompdf has no web fonts here, so this stays deliberately plain. */
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #16181D; margin: 0; }
        .head { border-bottom: 1px solid #E8EAED; padding-bottom: 10px; margin-bottom: 14px; }
        .business { font-size: 14pt; font-weight: bold; }
        .title { font-size: 12pt; margin-top: 4px; }
        .meta { color: #6B7280; font-size: 9pt; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-weight: normal; color: #6B7280; font-size: 9pt;
             border-bottom: 1px solid #E8EAED; padding: 6px 4px; }
        td { padding: 6px 4px; border-bottom: 1px solid #F1F2F4; }
        .right { text-align: right; }
        .totals { margin-top: 16px; border-top: 1px solid #E8EAED; padding-top: 10px; }
        .totals div { margin-bottom: 4px; }
        .totals .label { color: #6B7280; }
        .foot { margin-top: 18px; color: #9CA3AF; font-size: 8pt; }
    </style>
</head>
<body>
    <div class="head">
        <div class="business">{{ $business }}</div>
        <div class="title">{{ $report['title'] }}</div>
        <div class="meta">
            {{ $report['period']['label'] }}
            @if (! empty($report['branch'])) &middot; {{ $report['branch'] }} @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($report['columns'] as $column)
                    <th class="{{ ($column['align'] ?? '') === 'right' ? 'right' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($report['columns'] as $column)
                        @php $value = data_get($row, $column['key']); @endphp
                        <td class="{{ ($column['align'] ?? '') === 'right' ? 'right' : '' }}">
                            @if (($column['type'] ?? null) === 'money')
                                {{ $currency }}{{ number_format((float) $value, 2) }}
                            @else
                                {{ $value === null || $value === '' ? '—' : $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['columns']) }}" style="color:#6B7280">
                        Nothing to show for these dates.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (! empty($report['totals']))
        <div class="totals">
            @foreach ($report['totals'] as $label => $value)
                <div><span class="label">{{ $label }}:</span> <strong>{{ $value }}</strong></div>
            @endforeach
        </div>
    @endif

    <div class="foot">Printed {{ now()->format('j M Y, g:i a') }}</div>
</body>
</html>
