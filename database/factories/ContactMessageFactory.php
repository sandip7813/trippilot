<?php

namespace Database\Factories;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactTopic;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->numerify('98########'),
            'topic' => fake()->randomElement(ContactTopic::cases()),
            'subject' => fake()->sentence(5),
            'message' => fake()->paragraph(),
            'status' => ContactMessageStatus::New,
            'replied_at' => null,
        ];
    }

    public function fromUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function replied(): static
    {
        return $this->state(fn (): array => [
            'status' => ContactMessageStatus::Replied,
            'replied_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => ContactMessageStatus::Closed,
        ]);
    }
}
