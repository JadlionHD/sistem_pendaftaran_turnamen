<?php

use App\Models\Game;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();

    $this->admin = User::factory()->create([
        'name' => 'Admin Esports',
        'email' => 'admin@esports.com',
        'is_admin' => true,
    ]);

    $this->user = User::factory()->create([
        'name' => 'Peserta Biasa',
        'email' => 'peserta@esports.com',
        'is_admin' => false,
    ]);

    $this->game = Game::factory()->create([
        'name' => 'Mobile Legends: Bang Bang',
        'slug' => 'mobile-legends',
        'genre' => 'MOBA',
        'team_size' => 5,
        'platform' => 'Mobile',
        'is_active' => true,
    ]);

    $this->tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
        'title' => 'MLBB National Cup 2026',
        'rules' => 'Maksimal 5 pemain inti dan 1 cadangan. ID game harus valid.',
        'status' => 'open',
    ]);

    $this->registration = TournamentRegistration::factory()->create([
        'tournament_id' => $this->tournament->id,
        'user_id' => $this->user->id,
        'team_name' => 'Rex Regum Garuda',
        'captain_name' => 'Budi Santoso',
        'captain_whatsapp' => '08123456789',
        'captain_email' => 'budi@rrg.com',
        'team_members' => "1. RRG_Budi (ID: 12345678, Zone: 1234)\n2. RRG_Doni (ID: 23456789, Zone: 1234)",
        'status' => 'pending',
    ]);

    Config::set('services.atmorouter.api_key', 'ar-test-secret-key-12345');
    Config::set('services.atmorouter.base_url', 'https://atmorouter.dev/v1');
    Config::set('services.atmorouter.model', 'atmo/deepseek-v4.1-flash');
});

test('admin dapat menjalankan audit AI pendaftaran tim via AtmoRouter dan menyimpan hasilnya', function () {
    Http::fake([
        'https://atmorouter.dev/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl-test-123',
            'object' => 'chat.completion',
            'model' => 'atmo/deepseek-v4.1-flash',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'status' => 'passed',
                            'score' => 95,
                            'summary' => 'Format ID Mobile Legends valid dan tidak ada duplikasi pemain.',
                            'checklist' => [
                                ['rule' => 'Kelengkapan Roster', 'passed' => true, 'note' => 'Format IGN & ID akun lengkap.'],
                                ['rule' => 'Pengecekan Duplikasi', 'passed' => true, 'note' => 'Tidak ditemukan ID di tim lain.'],
                                ['rule' => 'Etika & Kesopanan', 'passed' => true, 'note' => 'Nama tim dan nickname sopan.'],
                            ],
                            'recommendation' => 'Setujui pendaftaran tim ini.',
                        ]),
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 120,
                'completion_tokens' => 80,
                'total_tokens' => 200,
            ],
        ], 200, [
            'X-Atmorouter-Cost' => '$0.000145',
        ]),
    ]);

    $response = $this->actingAs($this->admin)
        ->post(route('registrations.audit-ai', $this->registration->id));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->registration->refresh();

    expect($this->registration->ai_status)->toBe('passed')
        ->and($this->registration->ai_score)->toBe(95)
        ->and($this->registration->ai_summary)->toContain('Format ID Mobile Legends valid')
        ->and($this->registration->ai_recommendation)->toBe('Setujui pendaftaran tim ini.')
        ->and($this->registration->ai_cost)->toBe('$0.000145')
        ->and($this->registration->ai_tokens_used)->toBe(200)
        ->and($this->registration->ai_checked_at)->not->toBeNull()
        ->and($this->registration->ai_checklist)->toBeArray()
        ->and(count($this->registration->ai_checklist))->toBe(3);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://atmorouter.dev/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer ar-test-secret-key-12345')
            && $request['model'] === 'atmo/deepseek-v4.1-flash'
            && str_contains($request['messages'][1]['content'], 'Rex Regum Garuda');
    });
});

test('peserta biasa ditolak 403 saat mencoba memicu audit AI', function () {
    $response = $this->actingAs($this->user)
        ->post(route('registrations.audit-ai', $this->registration->id));

    $response->assertForbidden();
});

test('tamu tanpa autentikasi dialihkan ke halaman login', function () {
    $response = $this->post(route('registrations.audit-ai', $this->registration->id));

    $response->assertRedirect('/login');
});

test('audit AI menangani kondisi API key belum disetel dengan pesan yang jelas', function () {
    Config::set('services.atmorouter.api_key', '');

    $response = $this->actingAs($this->admin)
        ->post(route('registrations.audit-ai', $this->registration->id));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'API Key AtmoRouter belum dikonfigurasi di file .env (ATMOROUTER_API_KEY).');
});
