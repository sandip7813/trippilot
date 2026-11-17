<?php

use App\Enums\CreditTransactionType;
use App\Models\CreditCost;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\CreditsAddedNotification;
use App\Services\Credits\CreditCosts;
use App\Services\Credits\CreditLedger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Cache::flush();
});

test('super admins can add credits to a user', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($superAdmin)
        ->from(route('admin.users.index'))
        ->post(route('admin.super.users.credits.store', $user), [
            'credits' => 40,
            'note' => 'Compensation for a failed trip plan',
        ])
        ->assertRedirect(route('admin.users.index'));

    $grant = $user->creditTransactions()->sole();

    expect($user->fresh()->credit_balance)->toBe(40)
        ->and($grant->type)->toBe(CreditTransactionType::AdminGrant)
        ->and($grant->granted_by)->toBe($superAdmin->id)
        ->and($grant->note)->toBe('Compensation for a failed trip plan');
});

test('users are told by email and in the bell when credits are added', function () {
    Notification::fake();
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($superAdmin)->post(route('admin.super.users.credits.store', $user), [
        'credits' => 40,
        'note' => 'Sorry about the outage',
    ]);

    Notification::assertSentTo($user, CreditsAddedNotification::class, function (CreditsAddedNotification $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $bell = $notification->toArray($user);

        return $notification->via($user) === ['mail', 'database']
            && $mail->subject === '40 credits were added to your account'
            && in_array('Note: Sorry about the outage', $mail->introLines, true)
            && in_array('Your balance is now 40 credits. Credits never expire.', $mail->introLines, true)
            && $mail->actionUrl === route('credits.history')
            && $bell['title'] === '40 credits were added to your account'
            && $bell['url'] === route('credits.history', absolute: false);
    });
    Notification::assertNotSentTo($superAdmin, CreditsAddedNotification::class);
});

test('the credits added notification shows in the user\'s notification bell', function () {
    $user = User::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.super.users.credits.store', $user), ['credits' => 25]);

    $this->actingAs($user)
        ->get(route('credits.history'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unread', 1)
            ->where('notifications.recent.0.kind', 'credits')
            ->where('notifications.recent.0.title', '25 credits were added to your account')
            ->where('notifications.recent.0.message', 'Your balance is now 25 credits.'));
});

test('no notification is sent when adding credits fails validation', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.super.users.credits.store', $user), ['credits' => 0]);

    Notification::assertNothingSent();
});

test('added credits appear in the super admin log and the user\'s credit history', function () {
    $superAdmin = User::factory()->superAdmin()->create(['first_name' => 'Meera', 'last_name' => 'Sen']);
    $user = User::factory()->create(['first_name' => 'Ravi', 'last_name' => 'Das']);

    $this->actingAs($superAdmin)->post(route('admin.super.users.credits.store', $user), [
        'credits' => 40,
        'note' => 'Thanks for the feedback',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.super.credit-grants.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/super/CreditGrants')
            ->has('grants.data', 1)
            ->where('grants.data.0.credits', 40)
            ->where('grants.data.0.user_name', 'Ravi Das')
            ->where('grants.data.0.granted_by_name', 'Meera Sen')
            ->where('grants.data.0.note', 'Thanks for the feedback')
            ->where('grants.data.0.created_at', fn (?string $date): bool => $date !== null));

    $this->actingAs($user)
        ->get(route('credits.history'))
        ->assertInertia(fn ($page) => $page
            ->where('transactions.data.0.type', 'admin_grant')
            ->where('transactions.data.0.amount', 40)
            ->where('transactions.data.0.note', 'Thanks for the feedback')
            ->where('transactions.data.0.created_at', fn (?string $date): bool => $date !== null));
});

test('the credits added log only lists credits added by super admins', function () {
    $user = User::factory()->create();
    app(CreditLedger::class)->grantSignupBonus($user, 30);
    app(CreditLedger::class)->grant($user, 15, User::factory()->superAdmin()->create());

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.super.credit-grants.index'))
        ->assertInertia(fn ($page) => $page
            ->has('grants.data', 1)
            ->where('grants.data.0.credits', 15));
});

test('regular admins cannot view the credits added log', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.super.credit-grants.index'))
        ->assertForbidden();
});

test('the users list shows each user\'s remaining credits', function () {
    $superAdmin = User::factory()->superAdmin()->create(['first_name' => 'Aaron']);
    $user = User::factory()->create(['first_name' => 'Zara']);
    app(CreditLedger::class)->grant($user, 18);

    $this->actingAs($superAdmin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page
            ->where('users.data.0.id', $superAdmin->id)
            ->where('users.data.0.uses_credits', false)
            ->where('users.data.0.can_grant_credits', false)
            ->where('users.data.1.id', $user->id)
            ->where('users.data.1.uses_credits', true)
            ->where('users.data.1.credit_balance', 18)
            ->where('users.data.1.can_grant_credits', true));
});

test('regular admins cannot add credits', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.super.users.credits.store', $user), ['credits' => 40])
        ->assertForbidden();

    expect($user->fresh()->credit_balance)->toBe(0);
});

test('credits cannot be added to admins because they never spend them', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.super.users.credits.store', $admin), ['credits' => 40])
        ->assertForbidden();
});

test('the number of credits to add must be a positive whole number', function (mixed $credits) {
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.super.users.credits.store', $user), ['credits' => $credits])
        ->assertSessionHasErrors(['credits']);
})->with([0, -5, 'ten', 10001]);

test('super admins can update credit costs and switch charging on', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.super.credits.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/super/Credits')
            ->where('enabled', false)
            ->where('services.0.credits', 12));

    $this->actingAs($superAdmin)
        ->from(route('admin.super.credits.show'))
        ->put(route('admin.super.credits.update'), [
            'enabled' => true,
            'costs' => [
                'itinerary_generation' => 15,
                'itinerary_regeneration' => 10,
                'trip_chat_message' => 2,
                'assistant_message' => 2,
                'road_break_suggestions' => 3,
            ],
        ])
        ->assertRedirect(route('admin.super.credits.show'));

    expect(app(CreditCosts::class)->all()['itinerary_generation'])->toBe(15)
        ->and(CreditCost::query()->sole()->updated_by)->toBe($superAdmin->id)
        ->and(PlatformSetting::query()->find('credits.enabled')?->value)->toBe('true');
});

test('credit costs must be whole numbers between 0 and 1000', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.super.credits.update'), [
            'enabled' => true,
            'costs' => [
                'itinerary_generation' => -1,
                'itinerary_regeneration' => 1001,
                'trip_chat_message' => 2,
                'assistant_message' => 2,
            ],
        ])
        ->assertSessionHasErrors([
            'costs.itinerary_generation',
            'costs.itinerary_regeneration',
            'costs.road_break_suggestions',
        ]);
});

test('regular admins cannot manage credit settings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.super.credits.show'))->assertForbidden();
    $this->actingAs($admin)->put(route('admin.super.credits.update'), [])->assertForbidden();
});
