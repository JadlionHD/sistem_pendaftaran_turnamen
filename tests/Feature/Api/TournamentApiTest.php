<?php

use App\Models\Game;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'name' => 'Admin Panitia',
        'email' => 'admin@turnamen.test',
        'password' => Hash::make('password'),
        'is_admin' => true,
    ]);

    $this->kapten1 = User::factory()->create([
        'name' => 'Andi Pratama',
        'email' => 'kapten1@turnamen.test',
        'password' => Hash::make('password'),
        'is_admin' => false,
    ]);

    $this->kapten2 = User::factory()->create([
        'name' => 'Budi Santoso',
        'email' => 'kapten2@turnamen.test',
        'password' => Hash::make('password'),
        'is_admin' => false,
    ]);

    $this->game = Game::factory()->create([
        'name' => 'Mobile Legends: Bang Bang',
        'slug' => 'mobile-legends',
        'is_active' => true,
    ]);

    $this->tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
        'title' => 'MLBB Championship 2026',
        'status' => 'open',
        'max_teams' => 16,
        'registration_deadline' => now()->addDays(5)->toDateString(),
        'start_date' => now()->addDays(7)->toDateString(),
    ]);
});

test('api login generates sanctum token for valid credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'kapten1@turnamen.test',
        'password' => 'password',
        'device_name' => 'postman-test',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token_type', 'access_token', 'expires_at', 'user']);
});

test('api me requires authentication and returns user data', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();

    $this->actingAs($this->kapten1, 'sanctum')
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $this->kapten1->id);
});

test('api registration happy path, ownership restriction, and negative paths work correctly', function () {
    // 1. Kapten1 mendaftar
    $payload = [
        'tournament_id' => $this->tournament->id,
        'team_name' => 'Garuda Esports',
        'captain_name' => 'Andi Pratama',
        'captain_whatsapp' => '081234567890',
        'captain_email' => 'kapten1@turnamen.test',
        'team_members' => "1. Andi\n2. Budi\n3. Candra\n4. Dedi\n5. Erik",
    ];

    $response = $this->actingAs($this->kapten1, 'sanctum')
        ->postJson('/api/v1/registrations', $payload);

    $response->assertCreated()
        ->assertHeader('Location')
        ->assertJsonPath('data.team_name', 'Garuda Esports')
        ->assertJsonPath('data.owner.id', $this->kapten1->id);

    $regId = $response->json('data.id');

    // 2. Kapten2 tidak boleh membaca registrasi Kapten1 (403 Forbidden)
    $this->actingAs($this->kapten2, 'sanctum')
        ->getJson("/api/v1/registrations/{$regId}")
        ->assertForbidden();

    // 3. Kapten2 tidak boleh mengubah registrasi Kapten1 (403 Forbidden)
    $this->actingAs($this->kapten2, 'sanctum')
        ->putJson("/api/v1/registrations/{$regId}", [
            ...$payload,
            'team_name' => 'Hacked Name',
        ])->assertForbidden();

    // 4. Kapten1 tidak boleh approve status sendiri (403 Forbidden)
    $this->actingAs($this->kapten1, 'sanctum')
        ->patchJson("/api/v1/registrations/{$regId}/status", [
            'status' => 'approved',
        ])->assertForbidden();

    // 5. Admin boleh approve status
    $this->actingAs($this->admin, 'sanctum')
        ->patchJson("/api/v1/registrations/{$regId}/status", [
            'status' => 'approved',
            'admin_notes' => 'Lengkap dan lunas.',
        ])->assertOk()
        ->assertJsonPath('data.status', 'approved');

    // 6. Registrasi yang sudah approved tidak boleh diedit atau dihapus oleh peserta
    $this->actingAs($this->kapten1, 'sanctum')
        ->putJson("/api/v1/registrations/{$regId}", [
            ...$payload,
            'team_name' => 'Nama Baru Setelah Approved',
        ])->assertForbidden();

    $this->actingAs($this->kapten1, 'sanctum')
        ->deleteJson("/api/v1/registrations/{$regId}")
        ->assertForbidden();
});

test('api reports summary is restricted to admin', function () {
    $this->actingAs($this->kapten1, 'sanctum')
        ->getJson('/api/v1/reports/summary')
        ->assertForbidden();

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/reports/summary')
        ->assertOk()
        ->assertJsonStructure(['status', 'data' => ['tournament_count', 'registration_count', 'game_count']]);
});

test('api handles non-numeric id gracefully with 404 on postgresql', function () {
    $this->getJson('/api/v1/tournaments/turnamen-fiktif-abc')
        ->assertNotFound();

    $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/v1/registrations/registrasi-fiktif-abc')
        ->assertNotFound();
});
