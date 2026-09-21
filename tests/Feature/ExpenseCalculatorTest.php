<?php

use App\Enums\ExpenseEntryType;
use App\Enums\ExpenseSplitType;
use App\Models\TripExpenseEntry;
use App\Models\TripExpenseSheet;
use App\Services\Expenses\ExpenseCalculator;
use App\Services\Expenses\ExpenseSplitter;

function calcSheet(): TripExpenseSheet
{
    return new TripExpenseSheet([
        'participants' => collect(['a', 'b', 'c'])->map(fn (string $id): array => [
            'id' => $id, 'name' => strtoupper($id), 'email' => "{$id}@example.com", 'phone' => null, 'user_id' => null, 'archived' => false,
        ])->all(),
    ]);
}

/**
 * @param  list<array{0: string, 1: int}>  $payers
 * @param  list<array{0: string, 1: int}>  $splits
 */
function calcEntry(ExpenseEntryType $type, int $amount, array $payers, array $splits, ?string $category = 'stay'): TripExpenseEntry
{
    return new TripExpenseEntry([
        'type' => $type,
        'category' => $category,
        'amount' => $amount,
        'entry_date' => '2026-10-01',
        'payers' => array_map(fn (array $row): array => ['participant_id' => $row[0], 'amount' => $row[1]], $payers),
        'splits' => array_map(fn (array $row): array => ['participant_id' => $row[0], 'amount' => $row[1]], $splits),
    ]);
}

function balancesOf(array $result): array
{
    return collect($result['participants'])->pluck('balance', 'participant_id')->all();
}

test('equal split hands the leftover paise out so the parts always add up', function () {
    $splits = (new ExpenseSplitter)->resolve(ExpenseSplitType::Equal, 1000, [
        ['participant_id' => 'a'], ['participant_id' => 'b'], ['participant_id' => 'c'],
    ]);

    expect(array_sum(array_column($splits, 'amount')))->toBe(1000)
        ->and(collect($splits)->pluck('amount')->sort()->values()->all())->toBe([333, 333, 334]);
});

test('percentage and share splits resolve to exact amounts', function () {
    $splitter = new ExpenseSplitter;

    $percent = $splitter->resolve(ExpenseSplitType::Percentage, 10000, [
        ['participant_id' => 'a', 'value' => 70], ['participant_id' => 'b', 'value' => 30],
    ]);
    $shares = $splitter->resolve(ExpenseSplitType::Shares, 9000, [
        ['participant_id' => 'a', 'value' => 2], ['participant_id' => 'b', 'value' => 1],
    ]);

    expect($percent)->toBe([['participant_id' => 'a', 'amount' => 7000], ['participant_id' => 'b', 'amount' => 3000]])
        ->and($shares)->toBe([['participant_id' => 'a', 'amount' => 6000], ['participant_id' => 'b', 'amount' => 3000]]);
});

test('invalid splits are rejected', function () {
    $splitter = new ExpenseSplitter;

    expect(fn () => $splitter->resolve(ExpenseSplitType::Percentage, 1000, [['participant_id' => 'a', 'value' => 60], ['participant_id' => 'b', 'value' => 30]]))
        ->toThrow(InvalidArgumentException::class, 'Percentages must add up to 100%.')
        ->and(fn () => $splitter->resolve(ExpenseSplitType::Exact, 1000, [['participant_id' => 'a', 'value' => 5]]))
        ->toThrow(InvalidArgumentException::class, 'The split amounts must add up to the total.')
        ->and(fn () => $splitter->resolve(ExpenseSplitType::Equal, 1000, []))
        ->toThrow(InvalidArgumentException::class);
});

test('balances reflect who paid and who owes', function () {
    $result = (new ExpenseCalculator)->compute(calcSheet(), [
        calcEntry(ExpenseEntryType::Payment, 90000, [['a', 90000]], [['a', 30000], ['b', 30000], ['c', 30000]]),
    ]);

    expect(balancesOf($result))->toBe(['a' => 60000, 'b' => -30000, 'c' => -30000])
        ->and($result['totals']['net'])->toBe(90000)
        ->and($result['settlements'])->toHaveCount(2);
});

test('payments by different people add up in the category totals', function () {
    $result = (new ExpenseCalculator)->compute(calcSheet(), [
        calcEntry(ExpenseEntryType::Payment, 20000, [['a', 20000]], [['a', 10000], ['b', 10000]]),
        calcEntry(ExpenseEntryType::Payment, 40000, [['b', 40000]], [['a', 20000], ['b', 20000]]),
    ]);

    expect($result['categories'])->toBe([['category' => 'stay', 'net' => 60000]])
        ->and(balancesOf($result))->toBe(['a' => -10000, 'b' => 10000, 'c' => 0]);
});

test('a refund reduces spend and moves the credit to the chosen people', function () {
    $result = (new ExpenseCalculator)->compute(calcSheet(), [
        calcEntry(ExpenseEntryType::Payment, 100000, [['a', 100000]], [['a', 50000], ['b', 50000]]),
        calcEntry(ExpenseEntryType::Refund, 80000, [['c', 80000]], [['a', 40000], ['b', 40000]]),
    ]);

    expect($result['totals'])->toBe(['gross' => 100000, 'refunded' => 80000, 'net' => 20000])
        ->and($result['categories'])->toBe([['category' => 'stay', 'net' => 20000]])
        ->and(balancesOf($result))->toBe(['a' => 100000 - 50000 + 40000, 'b' => -50000 + 40000, 'c' => -80000])
        ->and(array_sum(balancesOf($result)))->toBe(0);
});

test('a personal payment settles debt without counting as spend', function () {
    $result = (new ExpenseCalculator)->compute(calcSheet(), [
        calcEntry(ExpenseEntryType::Payment, 90000, [['a', 90000]], [['a', 30000], ['b', 30000], ['c', 30000]]),
        calcEntry(ExpenseEntryType::Transfer, 30000, [['b', 30000]], [['a', 30000]], null),
    ]);

    expect($result['totals']['net'])->toBe(90000)
        ->and(balancesOf($result))->toBe(['a' => 30000, 'b' => 0, 'c' => -30000])
        ->and($result['settlements'])->toBe([['from' => 'c', 'to' => 'a', 'amount' => 30000]]);
});
