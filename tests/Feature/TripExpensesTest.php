<?php

use App\Enums\TripCollaboratorRole;
use App\Mail\TripExpenseReportMail;
use App\Mail\TripExpenseSheetReopenedMail;
use App\Mail\TripExpenseSheetSettledMail;
use App\Models\Trip;
use App\Models\TripExpenseActivity;
use App\Models\TripExpenseEntry;
use App\Models\TripExpenseSheet;
use App\Models\User;
use App\Services\Expenses\ExpenseSheetPresenter;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    skipUnlessMongoDbAvailable();

    Trip::query()->whereNotNull('_id')->delete();
    TripExpenseSheet::query()->whereNotNull('_id')->delete();
    TripExpenseEntry::withTrashed()->whereNotNull('_id')->forceDelete();
    TripExpenseActivity::query()->whereNotNull('_id')->delete();
});

/**
 * @return array{0: User, 1: Trip}
 */
function expenseTrip(): array
{
    $owner = User::factory()->create();

    return [$owner, Trip::factory()->forUser($owner)->create()];
}

function expenseSheet(Trip $trip, User $owner): TripExpenseSheet
{
    test()->actingAs($owner)->post(route('trips.expenses.sheet.store', $trip), [
        'participants' => [
            ['name' => 'Asha', 'email' => $owner->email],
            ['name' => 'Bala', 'email' => 'bala@example.com', 'phone' => '9999999999'],
            ['name' => 'Chitra', 'email' => 'chitra@example.com'],
        ],
    ])->assertRedirect();

    return $trip->expenseSheet();
}

function participantId(TripExpenseSheet $sheet, string $name): string
{
    return collect($sheet->participantList())->firstWhere('name', $name)['id'];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paymentPayload(TripExpenseSheet $sheet, array $overrides = []): array
{
    $asha = participantId($sheet, 'Asha');

    return [
        'type' => 'payment',
        'category' => 'stay',
        'title' => 'Hotel advance',
        'entry_date' => '2026-10-01',
        'amount' => 3000,
        'payers' => [['participant_id' => $asha, 'amount' => 3000]],
        'split_type' => 'equal',
        'split_inputs' => collect(['Asha', 'Bala', 'Chitra'])->map(fn (string $name): array => ['participant_id' => participantId($sheet, $name)])->all(),
        ...$overrides,
    ];
}

test('an owner creates the sheet with outside participants and links portal users', function () {
    [$owner, $trip] = expenseTrip();

    $sheet = expenseSheet($trip, $owner);
    $asha = collect($sheet->participantList())->firstWhere('name', 'Asha');
    $bala = collect($sheet->participantList())->firstWhere('name', 'Bala');

    expect($sheet->participantList())->toHaveCount(3)
        ->and($asha['user_id'])->toBe($owner->id)
        ->and($bala['user_id'])->toBeNull()
        ->and($bala['phone'])->toBe('9999999999');
});

test('a trip only gets one sheet and participants need a name and email', function () {
    [$owner, $trip] = expenseTrip();
    expenseSheet($trip, $owner);

    $this->actingAs($owner)->post(route('trips.expenses.sheet.store', $trip), [
        'participants' => [['name' => 'Dev', 'email' => 'dev@example.com']],
    ])->assertSessionHasErrors('expense');

    $this->actingAs($owner)->post(route('trips.expenses.sheet.store', Trip::factory()->forUser($owner)->create()), [
        'participants' => [['name' => '', 'email' => 'nope']],
    ])->assertSessionHasErrors(['participants.0.name', 'participants.0.email']);
});

test('viewers and strangers cannot change the sheet', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $viewer = User::factory()->create();
    $trip->update(['collaborators' => [['user_id' => $viewer->id, 'email' => $viewer->email, 'role' => TripCollaboratorRole::Viewer->value, 'status' => 'accepted', 'added_at' => now()->toIso8601String()]]]);

    $this->actingAs($viewer)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet))->assertForbidden();
    $this->actingAs($viewer)->get(route('trips.expenses.export.csv', $trip))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('trips.expenses.export.csv', $trip))->assertForbidden();
    expect(TripExpenseEntry::query()->count())->toBe(0);
});

test('a payment with several payers is stored in paise and totals are calculated', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet, [
        'amount' => 3000.50,
        'payers' => [
            ['participant_id' => participantId($sheet, 'Asha'), 'amount' => 1000.50],
            ['participant_id' => participantId($sheet, 'Bala'), 'amount' => 2000],
        ],
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $entry = TripExpenseEntry::query()->first();
    $summary = app(ExpenseSheetPresenter::class)->sheet($trip, $trip->expenseSheet())['summary'];
    $balances = collect($summary['participants'])->pluck('balance', 'participant_id');

    expect($entry->amount)->toBe(300050)
        ->and(array_sum(array_column($entry->splits, 'amount')))->toBe(300050)
        ->and($summary['totals']['net'])->toBe(3000.5)
        ->and(round($balances->sum(), 2))->toBe(0.0)
        ->and($balances[participantId($sheet, 'Asha')])->toBe(0.33)
        ->and($balances[participantId($sheet, 'Bala')])->toBe(999.83)
        ->and($balances[participantId($sheet, 'Chitra')])->toBe(-1000.16);
});

test('payers that do not add up to the total are rejected', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);

    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet, [
        'payers' => [['participant_id' => participantId($sheet, 'Asha'), 'amount' => 100]],
    ]))->assertSessionHasErrors('expense');

    expect(TripExpenseEntry::query()->count())->toBe(0);
});

test('refunds and personal payments are recorded and adjust balances', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $asha = participantId($sheet, 'Asha');
    $bala = participantId($sheet, 'Bala');
    $chitra = participantId($sheet, 'Chitra');

    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet, ['title' => 'Train tickets']))->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), [
        'type' => 'refund', 'title' => 'Train cancelled', 'entry_date' => '2026-10-02', 'amount' => 2400,
        'payers' => [['participant_id' => $chitra, 'amount' => 2400]],
        'split_type' => 'equal', 'split_inputs' => [['participant_id' => $asha], ['participant_id' => $bala], ['participant_id' => $chitra]],
    ])->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), [
        'type' => 'transfer', 'title' => 'Paid personally', 'entry_date' => '2026-10-03', 'amount' => 1000,
        'payers' => [['participant_id' => $bala, 'amount' => 1000]],
        'split_type' => 'exact', 'split_inputs' => [['participant_id' => $asha]],
    ])->assertSessionHasNoErrors();

    $summary = app(ExpenseSheetPresenter::class)->sheet($trip, $trip->expenseSheet())['summary'];
    $balances = collect($summary['participants'])->pluck('balance', 'participant_id');

    expect($summary['totals'])->toMatchArray(['gross' => 3000.0, 'refunded' => 2400.0, 'net' => 600.0])
        ->and($balances[$asha])->toBe(1800.0)
        ->and($balances[$bala])->toBe(800.0)
        ->and($balances[$chitra])->toBe(-2600.0)
        ->and(round($balances->sum(), 2))->toBe(0.0);
});

test('an editor can change someone elses entry and it is flagged in the activity log', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $editor = User::factory()->create();
    $trip->update(['collaborators' => [['user_id' => $editor->id, 'email' => $editor->email, 'role' => TripCollaboratorRole::Editor->value, 'status' => 'accepted', 'added_at' => now()->toIso8601String()]]]);

    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet))->assertSessionHasNoErrors();
    $entry = TripExpenseEntry::query()->first();

    $this->actingAs($editor)->patch(route('trips.expenses.entries.update', [$trip, (string) $entry->id]), paymentPayload($sheet, [
        'amount' => 6000,
        'payers' => [['participant_id' => participantId($sheet, 'Asha'), 'amount' => 6000]],
    ]))->assertSessionHasNoErrors();
    $this->actingAs($editor)->delete(route('trips.expenses.entries.destroy', [$trip, (string) $entry->id]))->assertRedirect();

    $activities = TripExpenseActivity::query()->where('entry_id', (string) $entry->id)->get()->keyBy('action');

    expect($activities['entry_created']->by_other)->toBeFalse()
        ->and($activities['entry_updated']->by_other)->toBeTrue()
        ->and($activities['entry_updated']->summary)->toContain('amount')
        ->and($activities['entry_updated']->actor['name'])->toBe($editor->name)
        ->and($activities['entry_deleted']->by_other)->toBeTrue()
        ->and(TripExpenseEntry::query()->count())->toBe(0)
        ->and(TripExpenseEntry::withTrashed()->count())->toBe(1);
});

test('a participant used in entries is archived instead of removed', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet));

    $this->actingAs($owner)->delete(route('trips.expenses.participants.destroy', [$trip, participantId($sheet, 'Chitra')]))->assertRedirect();

    $chitra = collect($trip->expenseSheet()->participantList())->firstWhere('name', 'Chitra');
    expect($chitra['archived'])->toBeTrue();
});

test('settling locks the sheet, snapshots balances and emails participants', function () {
    Mail::fake();
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet));

    $this->actingAs($owner)->post(route('trips.expenses.settle', $trip), ['notify_participants' => true])->assertRedirect();

    $sheet = $trip->expenseSheet();
    expect($sheet->isSettled())->toBeTrue()
        ->and($sheet->settlement_snapshot['totals']['net'])->toBe(3000.0)
        ->and($sheet->settled_by['user_id'])->toBe($owner->id);
    Mail::assertQueued(TripExpenseSheetSettledMail::class, 3);

    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet))->assertStatus(423);
    $this->actingAs($owner)->post(route('trips.expenses.participants.store', $trip), ['name' => 'Dev', 'email' => 'dev@example.com'])->assertStatus(423);
    $this->actingAs($owner)->get(route('trips.expenses.export.csv', $trip))->assertOk();
    expect(TripExpenseEntry::query()->count())->toBe(1);
});

test('settling can skip the summary email', function () {
    Mail::fake();
    [$owner, $trip] = expenseTrip();
    expenseSheet($trip, $owner);

    $this->actingAs($owner)->post(route('trips.expenses.settle', $trip), ['notify_participants' => false])->assertRedirect();

    Mail::assertNothingQueued();
});

test('reopening needs a reason, unlocks the sheet and emails every participant', function () {
    Mail::fake();
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $this->actingAs($owner)->post(route('trips.expenses.settle', $trip), ['notify_participants' => false]);

    $this->actingAs($owner)->post(route('trips.expenses.reopen', $trip), ['reason' => ''])->assertSessionHasErrors('reason');
    expect($trip->expenseSheet()->isSettled())->toBeTrue();

    $this->actingAs($owner)->post(route('trips.expenses.reopen', $trip), ['reason' => 'Forgot the taxi fare'])->assertRedirect();

    expect($trip->expenseSheet()->isSettled())->toBeFalse()
        ->and($trip->expenseSheet()->settlement_snapshot)->toBeNull()
        ->and(TripExpenseActivity::query()->where('action', 'sheet_reopened')->first()->summary)->toContain('Forgot the taxi fare');
    Mail::assertQueued(TripExpenseSheetReopenedMail::class, 3);
    Mail::assertQueued(TripExpenseSheetReopenedMail::class, fn (TripExpenseSheetReopenedMail $mail) => $mail->hasTo('bala@example.com') && $mail->reopenedByName === $owner->name);

    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet))->assertSessionHasNoErrors();
});

test('csv export includes every section and neutralises spreadsheet formulas', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet, ['title' => '=HYPERLINK("http://evil")']));

    $response = $this->actingAs($owner)->get(route('trips.expenses.export.csv', $trip))->assertOk();
    $csv = $response->getContent();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($csv)->toContain('Balances', 'Suggested settlements', 'Activity log', 'Asha', "'=HYPERLINK");
});

test('pdf export returns a pdf document', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet));

    $response = $this->actingAs($owner)->get(route('trips.expenses.export.pdf', $trip))->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('the report can be emailed to any address but only five at a time', function () {
    Mail::fake();
    [$owner, $trip] = expenseTrip();
    expenseSheet($trip, $owner);

    $this->actingAs($owner)->post(route('trips.expenses.email', $trip), [
        'recipients' => ['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com', 'f@example.com'],
        'format' => 'pdf',
    ])->assertSessionHasErrors('recipients');
    Mail::assertNothingQueued();

    $this->actingAs($owner)->post(route('trips.expenses.email', $trip), [
        'recipients' => ['outsider@example.com', 'friend@example.com'],
        'format' => 'csv',
        'message' => 'Here you go',
    ])->assertRedirect();

    Mail::assertQueued(TripExpenseReportMail::class, 2);
    Mail::assertQueued(TripExpenseReportMail::class, fn (TripExpenseReportMail $mail) => $mail->hasTo('outsider@example.com') && $mail->format === 'csv');
});

test('deleting a trip removes its expense data', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet));

    $trip->delete();

    expect(TripExpenseSheet::query()->count())->toBe(0)
        ->and(TripExpenseEntry::withTrashed()->count())->toBe(0)
        ->and(TripExpenseActivity::query()->count())->toBe(0);
});

test('the trip page defers the expenses payload and shows viewers a read-only sheet', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);
    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet));
    $viewer = User::factory()->create();
    $trip->update(['collaborators' => [['user_id' => $viewer->id, 'email' => $viewer->email, 'role' => TripCollaboratorRole::Viewer->value, 'status' => 'accepted', 'added_at' => now()->toIso8601String()]]]);

    $this->actingAs($viewer)->get(route('trips.show', $trip))
        ->assertInertia(fn ($page) => $page
            ->missing('expenses')
            ->loadDeferredProps('expenses', fn ($reload) => $reload
                ->where('expenses.can_manage', false)
                ->where('expenses.sheet.status', 'open')
                ->has('expenses.sheet.entries', 1)
                ->where('expenses.sheet.summary.totals.net', 3000)));
});

test('a road trip shares the same expense sheet', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->forUser($owner)->road()->create();

    $this->actingAs($owner)->post(route('trips.expenses.sheet.store', $trip), [
        'participants' => [['name' => 'Asha', 'email' => 'asha@example.com']],
    ])->assertRedirect();

    $this->actingAs($owner)->get(route('road-trips.show', $trip))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('expenses', fn ($reload) => $reload
            ->where('expenses.can_manage', true)
            ->has('expenses.sheet.participants', 1)));
});

test('each row carries its own category and falls back to other', function () {
    [$owner, $trip] = expenseTrip();
    $sheet = expenseSheet($trip, $owner);

    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet, ['title' => 'Tea', 'category' => 'food', 'amount' => 30, 'payers' => [['participant_id' => participantId($sheet, 'Asha'), 'amount' => 30]]]))->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('trips.expenses.entries.store', $trip), paymentPayload($sheet, ['title' => 'Cab', 'category' => null, 'amount' => 200, 'payers' => [['participant_id' => participantId($sheet, 'Bala'), 'amount' => 200]]]))->assertSessionHasNoErrors();

    $categories = collect(app(ExpenseSheetPresenter::class)->sheet($trip, $trip->expenseSheet())['summary']['categories'])->pluck('net', 'category');

    expect($categories->all())->toBe(['other' => 200.0, 'food' => 30.0]);
});
