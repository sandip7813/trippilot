<?php

namespace App\Actions\Expenses;

use App\Exceptions\ExpenseSheetException;
use App\Models\TripExpenseEntry;
use App\Models\TripExpenseSheet;
use App\Models\User;
use Illuminate\Support\Str;

class ManageExpenseParticipants
{
    public const MAX_PARTICIPANTS = 30;

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $data
     * @return array{id: string, name: string, email: string, phone: string|null, user_id: int|null, archived: bool}
     */
    public function add(TripExpenseSheet $sheet, array $data): array
    {
        $email = strtolower(trim($data['email']));
        $participants = $sheet->participantList();

        if (count($participants) >= self::MAX_PARTICIPANTS) {
            throw new ExpenseSheetException('An expense sheet can have at most '.self::MAX_PARTICIPANTS.' participants.');
        }

        if (collect($participants)->contains(fn (array $existing): bool => $existing['email'] === $email)) {
            throw new ExpenseSheetException("{$email} is already a participant.");
        }

        $participant = [
            'id' => (string) Str::ulid(),
            'name' => trim($data['name']),
            'email' => $email,
            'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
            'user_id' => User::query()->where('email', $email)->value('id'),
            'archived' => false,
        ];

        $sheet->update(['participants' => [...$participants, $participant]]);

        return $participant;
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $data
     */
    public function update(TripExpenseSheet $sheet, string $participantId, array $data): void
    {
        $email = strtolower(trim($data['email']));
        $participants = $sheet->participantList();

        if (collect($participants)->contains(fn (array $existing): bool => $existing['id'] !== $participantId && $existing['email'] === $email)) {
            throw new ExpenseSheetException("{$email} is already a participant.");
        }

        $sheet->update(['participants' => collect($participants)->map(function (array $existing) use ($participantId, $data, $email): array {
            if ($existing['id'] !== $participantId) {
                return $existing;
            }

            return [
                ...$existing,
                'name' => trim($data['name']),
                'email' => $email,
                'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                'user_id' => User::query()->where('email', $email)->value('id'),
            ];
        })->values()->all()]);
    }

    /**
     * Participants referenced by any entry are archived so history stays intact.
     *
     * @return bool True when the participant was removed, false when archived.
     */
    public function remove(TripExpenseSheet $sheet, string $participantId): bool
    {
        $isReferenced = TripExpenseEntry::withTrashed()
            ->where('sheet_id', (string) $sheet->id)
            ->where(fn ($query) => $query
                ->where('payers.participant_id', $participantId)
                ->orWhere('splits.participant_id', $participantId))
            ->exists();

        $participants = collect($sheet->participantList());

        $sheet->update(['participants' => $isReferenced
            ? $participants->map(fn (array $participant): array => $participant['id'] === $participantId ? [...$participant, 'archived' => true] : $participant)->values()->all()
            : $participants->reject(fn (array $participant): bool => $participant['id'] === $participantId)->values()->all()]);

        return ! $isReferenced;
    }
}
