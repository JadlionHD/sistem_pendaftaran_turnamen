<?php

namespace Database\Factories;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentRegistration>
 */
class TournamentRegistrationFactory extends Factory
{
    protected $model = TournamentRegistration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'user_id' => User::factory(),
            'team_name' => fake()->company().' Esports',
            'captain_name' => fake()->name(),
            'captain_whatsapp' => '08'.fake()->numerify('##########'),
            'captain_email' => fake()->safeEmail(),
            'team_members' => "1. PlayerOne (Kapten)\n2. PlayerTwo\n3. PlayerThree\n4. PlayerFour\n5. PlayerFive",
            'status' => 'pending',
            'admin_notes' => null,
        ];
    }
}
