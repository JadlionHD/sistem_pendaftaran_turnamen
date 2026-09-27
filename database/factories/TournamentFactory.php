<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    protected $model = Tournament::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3).' Championship';

        return [
            'game_id' => Game::factory(),
            'organizer_id' => User::factory()->state(['is_admin' => true]),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->paragraphs(2, true),
            'rules' => "1. Fair play diutamakan.\n2. Wajib hadir 15 menit sebelum pertandingan.\n3. Keputusan panitia mutlak.",
            'banner_image' => null,
            'max_teams' => fake()->randomElement([8, 16, 32]),
            'registration_fee' => fake()->randomElement([0, 50000, 100000]),
            'prize_pool' => 'Rp '.number_format(fake()->randomElement([1000000, 2500000, 5000000]), 0, ',', '.'),
            'registration_deadline' => now()->addDays(7)->toDateString(),
            'start_date' => now()->addDays(10)->toDateString(),
            'status' => 'open',
        ];
    }
}
