<?php

namespace Database\Factories;

use App\Enums\CreditOrderStatus;
use App\Models\CreditOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditOrder>
 */
class CreditOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'credit_pack_id' => null,
            'pack_name' => 'Explorer',
            'amount_paise' => 9900,
            'currency' => 'INR',
            'credits' => 110,
            'status' => CreditOrderStatus::Created,
            'razorpay_order_id' => 'order_'.Str::random(14),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CreditOrderStatus::Paid,
            'razorpay_payment_id' => 'pay_'.Str::random(14),
            'paid_at' => now(),
        ]);
    }
}
