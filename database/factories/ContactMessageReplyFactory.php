<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use App\Models\ContactMessageReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessageReply>
 */
class ContactMessageReplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_message_id' => ContactMessage::factory(),
            'user_id' => User::factory()->superAdmin(),
            'body' => fake()->paragraph(),
        ];
    }
}
