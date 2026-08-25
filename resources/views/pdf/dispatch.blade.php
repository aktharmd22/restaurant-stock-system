@extends('pdf.layout', [
    'title' => "Delivery note {$request['number']}",
    'docType' => 'Delivery note',
    'docNumber' => $request['number'],
    'docDate' => now()->format('j M Y'),
])

@section('body')
    {{--
        This sheet travels with the goods. It is what the store keeper walks
        the store with, what the driver carries, and what the branch signs.
        So: tick boxes on the left, a blank column on the right for what
        actually turned up, and room for two signatures at the bottom.
    --}}
    <table class="facts">
        <tr>
            <td>
                <div class="label">Going to</div>
                <div class="value">{{ $request['branch'] }}</div>
            </td>
            <td>
                <div class="label">Asked on</div>
                <div class="value">{{ $request['sent_at_text'] ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Items</div>
                <div class="value">{{ $totalLines }}</div>
            </td>
            <td>
                <div class="label">Taken by</div>
                <div class="value">{{ $carrier ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 22px"></th>
                <th>Item</th>
                <th class="right" style="width: 90px">Approved</th>
                <th class="right" style="width: 110px">Actually sent</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($packList as $group)
                <tr class="group">
                    <td colspan="4">{{ $group['location'] }}</td>
                </tr>

                @foreach ($group['lines'] as $line)
                    <tr>
                        <td><span class="tick"></span></td>
                        <td>{{ $line['item'] }}</td>
                        <td class="right num">{{ $line['approved_text'] }}</td>
                        <td class="right muted">
                            {{-- Left blank on purpose: filled in with a pen if
                                 what left the store is not what was approved. --}}
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="4" class="empty">Nothing was approved on this request.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (! empty($request['note']))
        <div class="note"><strong>The branch said:</strong> {{ $request['note'] }}</div>
    @endif

    <table class="sign">
        <tr>
            <td>
                <div class="line"></div>
                <div class="who">Packed by, at {{ $mainBranch }}</div>
            </td>
            <td>
                <div class="line"></div>
                <div class="who">Received at {{ $request['branch'] }} &middot; name, signature and time</div>
            </td>
        </tr>
    </table>

    <div class="note">
        Anything short or damaged goes in the "actually sent" column, with a reason. The branch
        confirms what arrived in the app, and the stock numbers follow that, not this sheet.
    </div>
@endsection
