<?php

namespace App\Services\Expenses;

use App\Exceptions\ExpenseSheetException;
use App\Models\Trip;
use App\Support\ExpenseMoney;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ExpenseReportBuilder
{
    public function __construct(private ExpenseSheetPresenter $presenter) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Trip $trip): array
    {
        $sheet = $trip->expenseSheet();

        if ($sheet === null) {
            throw new ExpenseSheetException('This trip has no expense sheet yet.');
        }

        $data = $this->presenter->sheet($trip, $sheet);
        $names = collect($data['participants'])->pluck('name', 'id');
        $name = fn (?string $id): string => (string) ($names->get($id) ?? 'Unknown');
        $people = fn (array $rows): string => collect($rows)
            ->map(fn (array $row): string => $name($row['participant_id']).' ('.ExpenseMoney::formatMajor($row['amount']).')')
            ->implode(', ');

        return [
            'trip_title' => $trip->title,
            'generated_at' => now()->format('d M Y, H:i'),
            'status' => $data['status'],
            'settled_at' => $sheet->settled_at?->format('d M Y, H:i'),
            'settled_by' => $data['settled_by'],
            'totals' => $data['summary']['totals'],
            'budget' => $data['summary']['budget'],
            'balances' => collect($data['summary']['participants'])->map(fn (array $row): array => [
                'name' => $name($row['participant_id']),
                ...$row,
            ])->all(),
            'settlements' => collect($data['summary']['settlements'])->map(fn (array $row): array => [
                'from' => $name($row['from']),
                'to' => $name($row['to']),
                'amount' => $row['amount'],
            ])->all(),
            'categories' => $data['summary']['categories'],
            'entries' => collect($data['entries'])->map(fn (array $entry): array => [
                'date' => $entry['entry_date'],
                'type' => $entry['type_label'],
                'category' => (string) $entry['category_label'],
                'title' => $entry['title'],
                'amount' => $entry['amount'],
                'paid_by' => $people($entry['payers']),
                'split_between' => $people($entry['splits']),
                'notes' => (string) $entry['notes'],
                'added_by' => (string) $entry['created_by'],
            ])->all(),
            'activities' => collect($data['activities'])->map(fn (array $activity): array => [
                'when' => $activity['created_at'] !== null ? Carbon::parse($activity['created_at'])->format('d M Y, H:i') : '',
                'actor' => $activity['actor'],
                'summary' => $activity['summary'],
                'by_other' => $activity['by_other'],
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function pdf(array $report): string
    {
        return Pdf::loadView('pdf.trip-expenses', ['report' => $report])
            ->setPaper('a4')
            ->output();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function csv(array $report): string
    {
        $stream = fopen('php://temp', 'r+');
        $write = fn (array $row) => fputcsv($stream, array_map($this->safeCell(...), $row));

        $write(['Expense report', $report['trip_title']]);
        $write(['Generated', $report['generated_at']]);
        $write(['Status', $report['status'] === 'settled' ? "Settled on {$report['settled_at']} by {$report['settled_by']}" : 'Open']);
        $write([]);

        $write(['Summary']);
        $write(['Total spend (net)', $report['totals']['net']]);
        $write(['Payments', $report['totals']['gross']]);
        $write(['Refunds', $report['totals']['refunded']]);
        $write(['Budget', $report['budget'] ?? '']);
        $write([]);

        $write(['Balances (positive = is owed, negative = owes)']);
        $write(['Person', 'Paid', 'Share', 'Personal payments sent', 'Personal payments received', 'Balance']);
        foreach ($report['balances'] as $row) {
            $write([$row['name'], $row['paid'], $row['share'], $row['sent'], $row['received'], $row['balance']]);
        }

        $write([]);
        $write(['Suggested settlements']);
        $write(['From', 'To', 'Amount']);
        foreach ($report['settlements'] as $row) {
            $write([$row['from'], $row['to'], $row['amount']]);
        }

        $write([]);
        $write(['Spend by category']);
        $write(['Category', 'Net']);
        foreach ($report['categories'] as $row) {
            $write([$row['label'], $row['net']]);
        }

        $write([]);
        $write(['Entries']);
        $write(['Date', 'Type', 'Category', 'What', 'Amount', 'Paid by', 'Split between', 'Notes', 'Added by']);
        foreach ($report['entries'] as $row) {
            $write([$row['date'], $row['type'], $row['category'], $row['title'], $row['amount'], $row['paid_by'], $row['split_between'], $row['notes'], $row['added_by']]);
        }

        $write([]);
        $write(['Activity log']);
        $write(['When', 'Who', 'What', 'Changed by someone else']);
        foreach ($report['activities'] as $row) {
            $write([$row['when'], $row['actor'], $row['summary'], $row['by_other'] ? 'Yes' : '']);
        }

        rewind($stream);

        return "\xEF\xBB\xBF".stream_get_contents($stream);
    }

    public function filename(Trip $trip, string $format): string
    {
        return Str::slug($trip->title).'-expenses.'.$format;
    }

    /**
     * Guards against spreadsheet formula injection from user-entered text.
     */
    private function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
