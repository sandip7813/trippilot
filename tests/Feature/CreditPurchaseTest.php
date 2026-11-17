<?php

use App\Enums\CreditOrderStatus;
use App\Enums\CreditService;
use App\Enums\CreditTransactionType;
use App\Models\CreditOrder;
use App\Models\CreditPack;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\CreditPurchaseRefundedNotification;
use App\Notifications\CreditsPurchasedNotification;
use App\Services\Credits\CreditLedger;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

const RAZORPAY_TEST_KEY_SECRET = 'test_key_secret';
const RAZORPAY_TEST_WEBHOOK_SECRET = 'test_webhook_secret';

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();

    config([
        'trippilot.credits.enabled' => true,
        'integrations.payments.razorpay.key_id' => 'rzp_test_key',
        'integrations.payments.razorpay.key_secret' => RAZORPAY_TEST_KEY_SECRET,
        'integrations.payments.razorpay.webhook_secret' => RAZORPAY_TEST_WEBHOOK_SECRET,
    ]);
});

function checkoutSignature(CreditOrder $order, string $paymentId): string
{
    return hash_hmac('sha256', $order->razorpay_order_id.'|'.$paymentId, RAZORPAY_TEST_KEY_SECRET);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fakeRazorpayPayment(CreditOrder $order, string $paymentId, array $overrides = []): void
{
    Http::fake([
        "api.razorpay.com/v1/payments/{$paymentId}" => Http::response([
            'id' => $paymentId,
            'order_id' => $order->razorpay_order_id,
            'amount' => $order->amount_paise,
            'currency' => 'INR',
            'status' => 'captured',
            ...$overrides,
        ]),
    ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function postRazorpayWebhook(array $payload, ?string $signature = null): TestResponse
{
    $body = json_encode($payload);

    return test()->call(
        'POST',
        route('webhooks.razorpay'),
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, RAZORPAY_TEST_WEBHOOK_SECRET),
        ],
        content: $body,
    );
}

function paidOrderWithCredits(User $user, int $credits = 110, int $amountPaise = 9900): CreditOrder
{
    $order = CreditOrder::factory()->for($user)->create(['credits' => $credits, 'amount_paise' => $amountPaise]);
    fakeRazorpayPayment($order, 'pay_refund_test');

    test()->actingAs($user)->post(route('credits.orders.verify', $order), [
        'razorpay_payment_id' => 'pay_refund_test',
        'razorpay_signature' => checkoutSignature($order, 'pay_refund_test'),
    ]);

    return $order->fresh();
}

test('users can start buying a credit pack', function () {
    $user = User::factory()->create();
    $pack = CreditPack::factory()->create(['name' => 'Voyager', 'price_paise' => 19900, 'credits' => 230]);

    Http::fake([
        'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_abc123', 'amount' => 19900, 'currency' => 'INR']),
    ]);

    $this->actingAs($user)
        ->postJson(route('credits.orders.store'), ['credit_pack_id' => $pack->id])
        ->assertOk()
        ->assertJsonPath('checkout.key', 'rzp_test_key')
        ->assertJsonPath('checkout.order_id', 'order_abc123')
        ->assertJsonPath('checkout.amount', 19900);

    $order = $user->creditOrders()->sole();

    expect($order->razorpay_order_id)->toBe('order_abc123')
        ->and($order->amount_paise)->toBe(19900)
        ->and($order->credits)->toBe(230)
        ->and($order->status)->toBe(CreditOrderStatus::Created)
        ->and($user->fresh()->credit_balance)->toBe(0);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.razorpay.com/v1/orders'
        && $request['amount'] === 19900
        && $request['currency'] === 'INR');
});

test('credit packs cannot be bought when purchases are unavailable', function (Closure $setUp) {
    $user = $setUp();
    $pack = CreditPack::factory()->create();

    $this->actingAs($user)
        ->postJson(route('credits.orders.store'), ['credit_pack_id' => $pack->id])
        ->assertForbidden();

    expect(CreditOrder::query()->count())->toBe(0);
})->with([
    'admins never spend credits' => fn () => User::factory()->admin()->create(),
    'credit charging is off' => function () {
        config(['trippilot.credits.enabled' => false]);

        return User::factory()->create();
    },
    'Razorpay is not set up' => function () {
        config(['integrations.payments.razorpay.key_secret' => null]);

        return User::factory()->create();
    },
]);

test('a pack that is no longer on sale cannot be bought', function () {
    $pack = CreditPack::factory()->inactive()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('credits.orders.store'), ['credit_pack_id' => $pack->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credit_pack_id' => 'no longer available']);
});

test('a Razorpay error while starting a purchase is shown to the user', function () {
    $pack = CreditPack::factory()->create();
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['error' => ['description' => 'Bad request']], 500)]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('credits.orders.store'), ['credit_pack_id' => $pack->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credit_pack_id' => 'could not start the payment']);

    expect(CreditOrder::query()->count())->toBe(0);
});

test('a verified payment adds the pack credits exactly once', function () {
    $user = User::factory()->create();
    $order = CreditOrder::factory()->for($user)->create(['credits' => 110, 'amount_paise' => 9900]);
    fakeRazorpayPayment($order, 'pay_123');

    $payload = [
        'razorpay_payment_id' => 'pay_123',
        'razorpay_signature' => checkoutSignature($order, 'pay_123'),
    ];

    $this->actingAs($user)
        ->post(route('credits.orders.verify', $order), $payload)
        ->assertRedirect(route('credits.history'))
        ->assertInertiaFlash('toast.type', 'success');

    $this->actingAs($user)->post(route('credits.orders.verify', $order), $payload);

    $purchase = $user->creditTransactions()->sole();

    expect($user->fresh()->credit_balance)->toBe(110)
        ->and($purchase->type)->toBe(CreditTransactionType::Purchase)
        ->and($purchase->credit_order_id)->toBe($order->id)
        ->and($order->fresh()->status)->toBe(CreditOrderStatus::Paid)
        ->and($order->fresh()->razorpay_payment_id)->toBe('pay_123');
});

test('an authorized payment is captured before credits are added', function () {
    $user = User::factory()->create();
    $order = CreditOrder::factory()->for($user)->create();

    Http::fake([
        'api.razorpay.com/v1/payments/pay_auth/capture' => Http::response(['id' => 'pay_auth', 'status' => 'captured']),
        'api.razorpay.com/v1/payments/pay_auth' => Http::response([
            'id' => 'pay_auth',
            'order_id' => $order->razorpay_order_id,
            'amount' => $order->amount_paise,
            'status' => 'authorized',
        ]),
    ]);

    $this->actingAs($user)->post(route('credits.orders.verify', $order), [
        'razorpay_payment_id' => 'pay_auth',
        'razorpay_signature' => checkoutSignature($order, 'pay_auth'),
    ]);

    expect($user->fresh()->credit_balance)->toBe($order->credits);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/payments/pay_auth/capture')
        && $request['amount'] === $order->amount_paise);
});

test('payments that cannot be verified add no credits', function (Closure $tamper) {
    $user = User::factory()->create();
    $order = CreditOrder::factory()->for($user)->create();

    [$signature, $payment] = $tamper($order);
    fakeRazorpayPayment($order, 'pay_123', $payment);

    $this->actingAs($user)
        ->post(route('credits.orders.verify', $order), [
            'razorpay_payment_id' => 'pay_123',
            'razorpay_signature' => $signature,
        ])
        ->assertRedirect(route('credits.buy'))
        ->assertInertiaFlash('toast.type', 'error');

    expect($user->fresh()->credit_balance)->toBe(0)
        ->and($order->fresh()->status)->toBe(CreditOrderStatus::Created);
})->with([
    'forged signature' => fn (CreditOrder $order) => ['not-a-real-signature', []],
    'payment for another order' => fn (CreditOrder $order) => [checkoutSignature($order, 'pay_123'), ['order_id' => 'order_other']],
    'paid amount differs' => fn (CreditOrder $order) => [checkoutSignature($order, 'pay_123'), ['amount' => 100]],
    'payment failed' => fn (CreditOrder $order) => [checkoutSignature($order, 'pay_123'), ['status' => 'failed']],
]);

test('users cannot confirm another user\'s order', function () {
    $order = CreditOrder::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('credits.orders.verify', $order), [
            'razorpay_payment_id' => 'pay_123',
            'razorpay_signature' => checkoutSignature($order, 'pay_123'),
        ])
        ->assertForbidden();
});

test('the payment captured webhook adds credits once even after checkout confirmed it', function () {
    $user = User::factory()->create();
    $order = CreditOrder::factory()->for($user)->create(['credits' => 50, 'amount_paise' => 4900]);

    $payload = [
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => [
            'id' => 'pay_hook',
            'order_id' => $order->razorpay_order_id,
            'amount' => 4900,
            'status' => 'captured',
        ]]],
    ];

    postRazorpayWebhook($payload)->assertOk();
    postRazorpayWebhook([...$payload, 'event' => 'order.paid'])->assertOk();

    expect($user->fresh()->credit_balance)->toBe(50)
        ->and($order->fresh()->razorpay_payment_id)->toBe('pay_hook')
        ->and($user->creditTransactions()->count())->toBe(1);
});

test('webhooks with an invalid signature are rejected', function () {
    $order = CreditOrder::factory()->create();

    postRazorpayWebhook([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => [
            'id' => 'pay_hook',
            'order_id' => $order->razorpay_order_id,
            'amount' => $order->amount_paise,
            'status' => 'captured',
        ]]],
    ], 'forged')->assertStatus(400);

    expect($order->fresh()->status)->toBe(CreditOrderStatus::Created);
});

test('a full refund takes back only the credits the user has not spent', function () {
    $user = User::factory()->create();
    $order = paidOrderWithCredits($user, credits: 110, amountPaise: 9900);
    $user->refresh();
    app(CreditLedger::class)->spend($user, CreditService::ItineraryGeneration, 30);

    $refund = [
        'event' => 'refund.processed',
        'payload' => [
            'refund' => ['entity' => ['id' => 'rfnd_1', 'payment_id' => 'pay_refund_test', 'amount' => 9900]],
            'payment' => ['entity' => ['id' => 'pay_refund_test', 'amount_refunded' => 9900]],
        ],
    ];

    postRazorpayWebhook($refund)->assertOk();
    postRazorpayWebhook($refund)->assertOk();

    $reversal = $user->creditTransactions()->where('type', CreditTransactionType::PurchaseReversal)->sole();

    expect($user->fresh()->credit_balance)->toBe(0)
        ->and($reversal->amount)->toBe(-80)
        ->and($order->fresh()->status)->toBe(CreditOrderStatus::Refunded);
});

test('a partial refund takes back the matching share of credits', function () {
    $user = User::factory()->create();
    $order = paidOrderWithCredits($user, credits: 100, amountPaise: 10000);

    postRazorpayWebhook([
        'event' => 'refund.processed',
        'payload' => [
            'refund' => ['entity' => ['id' => 'rfnd_1', 'payment_id' => 'pay_refund_test', 'amount' => 2500]],
            'payment' => ['entity' => ['id' => 'pay_refund_test', 'amount_refunded' => 2500]],
        ],
    ])->assertOk();

    expect($user->fresh()->credit_balance)->toBe(75)
        ->and($order->fresh()->status)->toBe(CreditOrderStatus::Paid)
        ->and($order->fresh()->credits_reversed)->toBe(25);
});

test('the buy credits page lists packs on sale', function () {
    CreditPack::query()->delete();
    CreditPack::factory()->create(['name' => 'Starter', 'price_paise' => 4900, 'credits' => 50, 'sort_order' => 1]);
    CreditPack::factory()->inactive()->create(['name' => 'Hidden']);

    $this->actingAs(User::factory()->create())
        ->get(route('credits.buy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('credits/Buy')
            ->where('unavailable_reason', null)
            ->has('packs', 1)
            ->where('packs.0.name', 'Starter')
            ->where('packs.0.price', 49));
});

test('the buy credits page explains why packs cannot be bought', function (Closure $setUp, string $reason) {
    $user = $setUp();

    $this->actingAs($user)
        ->get(route('credits.buy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('unavailable_reason', fn (string $message): bool => str_contains($message, $reason))
            ->has('packs', 0));
})->with([
    'admins' => [fn () => User::factory()->admin()->create(), 'As an admin'],
    'charging is off' => [function () {
        config(['trippilot.credits.enabled' => false]);

        return User::factory()->create();
    }, 'free for now'],
    'Razorpay is not set up' => [function () {
        config(['integrations.payments.razorpay.key_id' => null]);

        return User::factory()->create();
    }, 'not available yet'],
]);

test('super admins can edit credit packs', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    CreditPack::query()->delete();
    $pack = CreditPack::factory()->create();

    $this->actingAs($superAdmin)
        ->from(route('admin.super.credits.show'))
        ->put(route('admin.super.credit-packs.update'), [
            'packs' => [$pack->id => [
                'name' => 'Explorer Plus',
                'price' => 129,
                'credits' => 150,
                'is_featured' => '1',
                'is_active' => '0',
            ]],
        ])
        ->assertRedirect(route('admin.super.credits.show'));

    expect($pack->fresh())
        ->name->toBe('Explorer Plus')
        ->price_paise->toBe(12900)
        ->credits->toBe(150)
        ->is_featured->toBeTrue()
        ->is_active->toBeFalse();
});

test('credit pack prices and credits must be positive whole numbers', function () {
    CreditPack::query()->delete();
    $pack = CreditPack::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->put(route('admin.super.credit-packs.update'), [
            'packs' => [$pack->id => ['name' => '', 'price' => 0, 'credits' => 'many', 'is_featured' => '0', 'is_active' => '1']],
        ])
        ->assertSessionHasErrors(["packs.{$pack->id}.name", "packs.{$pack->id}.price", "packs.{$pack->id}.credits"]);
});

test('regular admins cannot edit credit packs', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.super.credit-packs.update'), ['packs' => []])
        ->assertForbidden();
});

test('super admins can save the Razorpay keys in integration settings', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->patch(route('admin.super.settings.integrations'), [
            'maps_driver' => 'geoapify',
            'weather_driver' => 'open_meteo',
            'ai_driver' => 'gemini',
            'trains_driver' => 'railradar',
            'trip_covers_driver' => 'rotating',
            'logo' => 'compass',
            'razorpay_key_id' => 'rzp_live_abc',
            'razorpay_key_secret' => 'live_secret',
            'razorpay_webhook_secret' => 'hook_secret',
        ])
        ->assertSessionHasNoErrors();

    expect(PlatformSetting::query()->find('payments.razorpay.key_id')?->value)->toBe('rzp_live_abc')
        ->and(PlatformSetting::query()->find('payments.razorpay.key_secret')?->value)->toBe('live_secret')
        ->and(PlatformSetting::query()->find('payments.razorpay.webhook_secret')?->value)->toBe('hook_secret');
});

test('buyers get one email receipt and bell notification per paid order', function () {
    Notification::fake();
    $user = User::factory()->create();
    $order = CreditOrder::factory()->for($user)->create(['pack_name' => 'Voyager', 'credits' => 230, 'amount_paise' => 19900]);
    fakeRazorpayPayment($order, 'pay_receipt');

    $this->actingAs($user)->post(route('credits.orders.verify', $order), [
        'razorpay_payment_id' => 'pay_receipt',
        'razorpay_signature' => checkoutSignature($order, 'pay_receipt'),
    ]);
    postRazorpayWebhook([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => [
            'id' => 'pay_receipt',
            'order_id' => $order->razorpay_order_id,
            'amount' => 19900,
            'status' => 'captured',
        ]]],
    ])->assertOk();

    Notification::assertSentToTimes($user, CreditsPurchasedNotification::class, 1);
    Notification::assertSentTo($user, CreditsPurchasedNotification::class, function (CreditsPurchasedNotification $notification) use ($user, $order): bool {
        $mail = $notification->toMail($user);
        $bell = $notification->toArray($user);

        return $notification->via($user) === ['mail', 'database']
            && $mail->subject === 'Payment received: 230 credits added'
            && in_array('We received your payment of ₹199 for the Voyager pack.', $mail->introLines, true)
            && in_array("Order number: TP-{$order->id}", $mail->introLines, true)
            && in_array('Payment ID: pay_receipt', $mail->introLines, true)
            && $bell['title'] === '230 credits added to your account'
            && $bell['url'] === route('credits.history', absolute: false);
    });
});

test('no purchase notification is sent when the payment cannot be verified', function () {
    Notification::fake();
    $user = User::factory()->create();
    $order = CreditOrder::factory()->for($user)->create();
    fakeRazorpayPayment($order, 'pay_bad');

    $this->actingAs($user)->post(route('credits.orders.verify', $order), [
        'razorpay_payment_id' => 'pay_bad',
        'razorpay_signature' => 'forged',
    ]);

    Notification::assertNothingSent();
});

test('users are told once when a refund takes back their unspent credits', function () {
    $user = User::factory()->create();
    $order = paidOrderWithCredits($user, credits: 110, amountPaise: 9900);
    app(CreditLedger::class)->spend($user->fresh(), CreditService::ItineraryGeneration, 30);
    Notification::fake();

    $refund = [
        'event' => 'refund.processed',
        'payload' => [
            'refund' => ['entity' => ['id' => 'rfnd_1', 'payment_id' => 'pay_refund_test', 'amount' => 9900]],
            'payment' => ['entity' => ['id' => 'pay_refund_test', 'amount_refunded' => 9900]],
        ],
    ];

    postRazorpayWebhook($refund)->assertOk();
    postRazorpayWebhook($refund)->assertOk();

    Notification::assertSentToTimes($user, CreditPurchaseRefundedNotification::class, 1);
    Notification::assertSentTo($user, CreditPurchaseRefundedNotification::class, function (CreditPurchaseRefundedNotification $notification) use ($user, $order): bool {
        $mail = $notification->toMail($user);
        $bell = $notification->toArray($user);

        return $mail->subject === 'Refund processed: ₹99'
            && in_array("Order number: TP-{$order->id}", $mail->introLines, true)
            && $bell['message'] === '80 unused credits from the Explorer pack were removed from your balance.';
    });
});

test('purchase notifications appear in the bell as credit notifications', function () {
    $user = User::factory()->create();
    paidOrderWithCredits($user);

    $this->actingAs($user)
        ->get(route('credits.history'))
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unread', 1)
            ->where('notifications.recent.0.kind', 'credits')
            ->where('notifications.recent.0.title', '110 credits added to your account'));
});
