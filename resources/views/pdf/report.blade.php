@extends('pdf.layout', [
    'title' => $report['title'],
    'docType' => 'Report',
    'docNumber' => $report['title'],
    'docDate' => $report['period']['label'],
])

@section('body')
    <table class="facts">
        <tr>
            <td>
                <div class="label">Covering</div>
                <div class="value">{{ $report['period']['label'] }}</div>
            </td>
            <td>
                <div class="label">Branch</div>
                <div class="value">{{ $report['branch'] ?: 'Every branch' }}</div>
            </td>
            <td>
                <div class="label">Rows</div>
                <div class="value">{{ count($report['rows']) }}</div>
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                @foreach ($report['columns'] as $column)
                    <th class="{{ ($column['align'] ?? '') === 'right' ? 'right' : '' }}">
                        {{ $column['label'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($report['columns'] as $column)
                        @php $value = data_get($row, $column['key']); @endphp
                        <td class="{{ ($column['align'] ?? '') === 'right' ? 'right num' : '' }}">
                            @if (($column['type'] ?? null) === 'money')
                                {{ money_in((float) $value, 2) }}
                            @else
                                {{ $value === null || $value === '' ? '—' : $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['columns']) }}" class="empty">
                        Nothing to show for these dates.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (! empty($report['totals']))
        <table class="totals">
            @foreach ($report['totals'] as $label => $value)
                <tr>
                    <td class="label">{{ $label }}</td>
                    <td class="value">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
