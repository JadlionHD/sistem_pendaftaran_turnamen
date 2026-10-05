<?php

use App\Models\Game;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();

    $this->admin = User::factory()->create([
        'name' => 'Organizer',
        'is_admin' => true,
    ]);

    $this->game = Game::factory()->create([
        'name' => 'Mobile Legends',
        'slug' => 'mobile-legends',
        'genre' => 'MOBA',
        'team_size' => 5,
        'platform' => 'Mobile',
        'is_active' => true,
    ]);

    $this->tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
        'title' => 'MLBB Garuda Championship',
        'rules' => 'Format 5v5 draft pick. Dilarang menggunakan cheat/skin script.',
        'status' => 'open',
    ]);
});

test('tamu (guest) dapat mengakses endpoint universal ai streaming tanpa login', function () {
    Config::set('services.atmorouter.api_key', '');

    $response = $this->post(route('ai-chat.stream'), [
        'message' => 'Turnamen apa saja yang tersedia di platform?',
    ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('API Key AtmoRouter belum dikonfigurasi')
        ->and($content)->toContain('data: [DONE]');
});

test('endpoint universal streaming memvalidasi pesan wajib diisi', function () {
    $response = $this->postJson(route('ai-chat.stream'), [
        'message' => '',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('message');
});

test('endpoint universal streaming menerima tournament_id opsional jika relevan', function () {
    Config::set('services.atmorouter.api_key', '');

    $response = $this->post(route('ai-chat.stream'), [
        'message' => 'Apakah kuota masih ada untuk turnamen ini?',
        'tournament_id' => $this->tournament->id,
    ]);

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('API Key AtmoRouter belum dikonfigurasi');
});

test('guest universal chat menerima pesan peringatan jika kuota token habis', function () {
    Config::set('services.atmorouter.guest_token_limit', 3000);
    $guestKey = 'ai_chat_universal_tokens_'.md5('127.0.0.1');
    Cache::put($guestKey, 3500, now()->addHours(1));

    $response = $this->post(route('ai-chat.stream'), [
        'message' => 'Jelaskan jadwal turnamen',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('Kamu telah melewati batas chat dengan AI nya')
        ->and($content)->toContain('token_limit_reached')
        ->and($content)->toContain('data: [DONE]');
});

test('guest dapat mereset kuota universal chat melalui endpoint reset', function () {
    Config::set('services.atmorouter.guest_token_limit', 3000);
    $guestKey = 'ai_chat_universal_tokens_'.md5('127.0.0.1');
    Cache::put($guestKey, 3500, now()->addHours(1));

    $response = $this->postJson(route('ai-chat.reset'));

    $response->assertOk();
    $response->assertJson(['success' => true]);

    expect(Cache::get($guestKey, 0))->toBe(0);
});

test('guest dapat meringkas riwayat percakapan universal melalui endpoint compact', function () {
    Http::fake([
        'https://atmorouter.dev/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl-compact-universal',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Pengguna bertanya tentang pendaftaran turnamen MLBB Garuda Championship dan format 5 pemain.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'total_tokens' => 45,
            ],
        ], 200),
    ]);

    Config::set('services.atmorouter.api_key', 'ar-test-key');
    Config::set('services.atmorouter.guest_token_limit', 3000);

    $guestKey = 'ai_chat_universal_tokens_'.md5('127.0.0.1');
    Cache::put($guestKey, 3500, now()->addHours(1));

    $response = $this->postJson(route('ai-chat.compact'), [
        'history' => [
            ['role' => 'user', 'content' => 'Bagaimana cara daftar turnamen MLBB?'],
            ['role' => 'assistant', 'content' => 'Pilih turnamen lalu klik Daftarkan Tim.'],
        ],
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'compacted_context' => 'Pengguna bertanya tentang pendaftaran turnamen MLBB Garuda Championship dan format 5 pemain.',
    ]);

    expect(Cache::get($guestKey, 0))->toBe(0);
});
