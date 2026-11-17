<?php

namespace Database\Factories;

use App\Models\ContentPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentPage>
 */
class ContentPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'slug' => str($title)->slug()->toString(),
            'title' => ucfirst($title),
            'description' => fake()->sentence(),
            'body' => "## Introduction\n\n".fake()->paragraph(),
            'is_published' => true,
            'show_in_footer' => true,
            'sort_order' => 10,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => false,
        ]);
    }
}
