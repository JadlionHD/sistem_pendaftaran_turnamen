<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Mobile Legends: Bang Bang',
            'Valorant',
            'PUBG Mobile',
            'Free Fire',
            'Dota 2',
            'Apex Legends Mobile',
            'Counter-Strike 2',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'genre' => fake()->randomElement(['MOBA', 'Tactical FPS', 'Battle Royale']),
            'team_size' => fake()->randomElement([4, 5]),
            'platform' => fake()->randomElement(['Mobile', 'PC', 'Multi-platform']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
