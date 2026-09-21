@php
    $money = fn ($amount) => \App\Support\ExpenseMoney::formatMajor($amount);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['trip_title'] }} expenses</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 18px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f3f4f6; }
        th, td { padding: 4px 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .flag { color: #b45309; }
        .pos { color: #047857; }
        .neg { color: #b91c1c; }
    </style>
</head>
<body>
    <h1>{{ $report['trip_title'] }}</h1>
    <div class="muted">
        Expense report &middot; generated {{ $report['generated_at'] }} &middot;
        @if ($report['status'] === 'settled')
            Settled on {{ $report['settled_at'] }} by {{ $report['settled_by'] }}
        @else
            Open
        @endif
    </div>

    <h2>Summary</h2>
    <table>
        <tr><td>Total spend (net)</td><td class="num"><strong>{{ $money($report['totals']['net']) }}</strong></td></tr>
        <tr><td>Payments</td><td class="num">{{ $money($report['totals']['gross']) }}</td></tr>
        <tr><td>Refunds</td><td class="num">{{ $money($report['totals']['refunded']) }}</td></tr>
        @if ($report['budget'])
            <tr><td>Budget</td><td class="num">{{ $money($report['budget']) }}</td></tr>
        @endif
    </table>

    <h2>Who paid and who owes</h2>
    <table>
        <tr><th>Person</th><th class="num">Paid</th><th class="num">Share</th><th class="num">Balance</th></tr>
        @foreach ($report['balances'] as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td class="num">{{ $money($row['paid']) }}</td>
                <td class="num">{{ $money($row['share']) }}</td>
                <td class="num {{ $row['balance'] >= 0 ? 'pos' : 'neg' }}">{{ $money($row['balance']) }}</td>
            </tr>
        @endforeach
    </table>
    <div class="muted">Positive balance: is owed money. Negative balance: owes money.</div>

    @if (count($report['settlements']) > 0)
        <h2>Suggested settlements</h2>
        <table>
            @foreach ($report['settlements'] as $row)
                <tr><td>{{ $row['from'] }} pays {{ $row['to'] }}</td><td class="num">{{ $money($row['amount']) }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Spend by category</h2>
    <table>
        <tr><th>Category</th><th class="num">Net</th></tr>
        @foreach ($report['categories'] as $row)
            <tr><td>{{ $row['label'] }}</td><td class="num">{{ $money($row['net']) }}</td></tr>
        @endforeach
    </table>

    <h2>Entries</h2>
    <table>
        <tr><th>Date</th><th>Type</th><th>What</th><th class="num">Amount</th><th>Paid by</th><th>Split between</th></tr>
        @foreach ($report['entries'] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['type'] }}</td>
                <td>
                    {{ $row['title'] }}
                    @if ($row['category'] !== '')
                        <span class="muted">({{ $row['category'] }})</span>
                    @endif
                    @if ($row['notes'] !== '')
                        <br><span class="muted">{{ $row['notes'] }}</span>
                    @endif
                </td>
                <td class="num">{{ $money($row['amount']) }}</td>
                <td>{{ $row['paid_by'] }}</td>
                <td>{{ $row['split_between'] }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Activity log</h2>
    <table>
        @forelse ($report['activities'] as $row)
            <tr class="{{ $row['by_other'] ? 'flag' : '' }}">
                <td>{{ $row['when'] }}</td>
                <td><strong>{{ $row['actor'] }}</strong> {{ $row['summary'] }}@if ($row['by_other']) (changed by someone other than the creator)@endif</td>
            </tr>
        @empty
            <tr><td class="muted">No activity yet.</td></tr>
        @endforelse
    </table>
</body>
</html>
