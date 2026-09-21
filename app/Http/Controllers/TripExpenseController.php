<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\CreateExpenseSheet;
use App\Actions\Expenses\DeleteExpenseEntry;
use App\Actions\Expenses\ManageExpenseParticipants;
use App\Actions\Expenses\ReopenExpenseSheet;
use App\Actions\Expenses\SaveExpenseEntry;
use App\Actions\Expenses\SettleExpenseSheet;
use App\Exceptions\ExpenseSheetException;
use App\Http\Requests\Expenses\EmailExpenseReportRequest;
use App\Http\Requests\Expenses\ExpenseEntryRequest;
use App\Http\Requests\Expenses\ExpenseParticipantRequest;
use App\Http\Requests\Expenses\ReopenExpenseSheetRequest;
use App\Http\Requests\Expenses\SettleExpenseSheetRequest;
use App\Http\Requests\Expenses\StoreExpenseSheetRequest;
use App\Mail\TripExpenseReportMail;
use App\Models\Trip;
use App\Models\TripExpenseEntry;
use App\Models\TripExpenseSheet;
use App\Services\Expenses\ExpenseActivityLogger;
use App\Services\Expenses\ExpenseReportBuilder;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TripExpenseController extends Controller
{
    public function storeSheet(StoreExpenseSheetRequest $request, Trip $trip, CreateExpenseSheet $createExpenseSheet): RedirectResponse
    {
        $this->authorize('manageExpenses', $trip);

        return $this->attempt(function () use ($request, $trip, $createExpenseSheet): void {
            $createExpenseSheet($trip, $request->user(), $request->validated('participants'));

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense sheet created.')]);
        });
    }

    public function storeParticipant(ExpenseParticipantRequest $request, Trip $trip, ManageExpenseParticipants $participants, ExpenseActivityLogger $logger): RedirectResponse
    {
        $sheet = $this->openSheet($trip);

        return $this->attempt(function () use ($request, $trip, $sheet, $participants, $logger): void {
            $participant = $participants->add($sheet, $request->validated());
            $logger->log($trip, $request->user(), 'participant_added', "added participant {$participant['name']}");
        });
    }

    public function updateParticipant(ExpenseParticipantRequest $request, Trip $trip, string $participantId, ManageExpenseParticipants $participants, ExpenseActivityLogger $logger): RedirectResponse
    {
        $sheet = $this->openSheet($trip);
        abort_if($sheet->findParticipant($participantId) === null, 404);

        return $this->attempt(function () use ($request, $trip, $sheet, $participantId, $participants, $logger): void {
            $participants->update($sheet, $participantId, $request->validated());
            $logger->log($trip, $request->user(), 'participant_updated', "updated participant {$request->validated('name')}");
        });
    }

    public function destroyParticipant(Request $request, Trip $trip, string $participantId, ManageExpenseParticipants $participants, ExpenseActivityLogger $logger): RedirectResponse
    {
        $sheet = $this->openSheet($trip);
        $participant = $sheet->findParticipant($participantId);
        abort_if($participant === null, 404);

        return $this->attempt(function () use ($request, $trip, $sheet, $participant, $participants, $logger): void {
            $removed = $participants->remove($sheet, $participant['id']);
            $logger->log($trip, $request->user(), 'participant_removed', ($removed ? 'removed' : 'archived')." participant {$participant['name']}");
        });
    }

    public function storeEntry(ExpenseEntryRequest $request, Trip $trip, SaveExpenseEntry $saveExpenseEntry): RedirectResponse
    {
        $sheet = $this->openSheet($trip);

        return $this->attempt(fn () => $saveExpenseEntry($trip, $sheet, $request->user(), $request->validated()));
    }

    public function updateEntry(ExpenseEntryRequest $request, Trip $trip, string $entry, SaveExpenseEntry $saveExpenseEntry): RedirectResponse
    {
        $sheet = $this->openSheet($trip);
        $entryModel = $this->entryFor($sheet, $entry);

        return $this->attempt(fn () => $saveExpenseEntry($trip, $sheet, $request->user(), $request->validated(), $entryModel));
    }

    public function destroyEntry(Request $request, Trip $trip, string $entry, DeleteExpenseEntry $deleteExpenseEntry): RedirectResponse
    {
        $sheet = $this->openSheet($trip);
        $entryModel = $this->entryFor($sheet, $entry);

        return $this->attempt(fn () => $deleteExpenseEntry($trip, $request->user(), $entryModel));
    }

    public function settle(SettleExpenseSheetRequest $request, Trip $trip, SettleExpenseSheet $settleExpenseSheet): RedirectResponse
    {
        $this->authorize('manageExpenses', $trip);
        $sheet = $this->sheetFor($trip);

        return $this->attempt(function () use ($request, $trip, $sheet, $settleExpenseSheet): void {
            $settleExpenseSheet($trip, $sheet, $request->user(), $request->boolean('notify_participants'));

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense sheet settled and locked.')]);
        });
    }

    public function reopen(ReopenExpenseSheetRequest $request, Trip $trip, ReopenExpenseSheet $reopenExpenseSheet): RedirectResponse
    {
        $this->authorize('manageExpenses', $trip);
        $sheet = $this->sheetFor($trip);

        return $this->attempt(function () use ($request, $trip, $sheet, $reopenExpenseSheet): void {
            $reopenExpenseSheet($trip, $sheet, $request->user(), $request->validated('reason'));

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense sheet reopened. Participants have been notified.')]);
        });
    }

    public function exportCsv(Trip $trip, ExpenseReportBuilder $reports): Response
    {
        $this->authorize('viewExpenses', $trip);

        return response($reports->csv($reports->build($trip)), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$reports->filename($trip, 'csv').'"',
        ]);
    }

    public function exportPdf(Trip $trip, ExpenseReportBuilder $reports): Response
    {
        $this->authorize('viewExpenses', $trip);

        return response($reports->pdf($reports->build($trip)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$reports->filename($trip, 'pdf').'"',
        ]);
    }

    public function emailReport(EmailExpenseReportRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('viewExpenses', $trip);
        $this->sheetFor($trip);

        foreach ($request->validated('recipients') as $email) {
            Mail::to($email)->queue(new TripExpenseReportMail(
                $trip,
                $request->validated('format'),
                $request->user()->name,
                $request->validated('message'),
            ));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Expense report is on its way.')]);

        return back();
    }

    private function sheetFor(Trip $trip): TripExpenseSheet
    {
        $sheet = $trip->expenseSheet();
        abort_if($sheet === null, 404);

        return $sheet;
    }

    /**
     * The sheet for a change: the caller must be allowed to edit and the sheet must not be settled.
     */
    private function openSheet(Trip $trip): TripExpenseSheet
    {
        $this->authorize('manageExpenses', $trip);

        $sheet = $this->sheetFor($trip);
        abort_if($sheet->isSettled(), 423, __('This expense sheet is settled. Reopen it to make changes.'));

        return $sheet;
    }

    private function entryFor(TripExpenseSheet $sheet, string $entryId): TripExpenseEntry
    {
        $entry = TripExpenseEntry::query()
            ->where('sheet_id', (string) $sheet->id)
            ->where('_id', $entryId)
            ->first();

        abort_if($entry === null, 404);

        return $entry;
    }

    private function attempt(Closure $operation): RedirectResponse
    {
        try {
            $operation();
        } catch (ExpenseSheetException $exception) {
            return back()->withErrors(['expense' => $exception->getMessage()]);
        }

        return back();
    }
}
