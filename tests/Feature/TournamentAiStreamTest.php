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
        'name' => 'Valorant',
        'slug' => 'valorant',
        'genre' => 'Tactical FPS',
        'team_size' => 5,
        'platform' => 'PC',
        'is_active' => true,
    ]);

    $this->tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
        'title' => 'Valorant Champions Cup',
        'rules' => 'Format 5v5 single elimination. No toxic behavior.',
        'status' => 'open',
    ]);
});

test('tamu (guest) dapat mengakses endpoint streaming ai tanpa harus login', function () {
    Config::set('services.atmorouter.api_key', '');

    $response = $this->post(route('tournaments.ai-chat-stream', $this->tournament->id), [
        'message' => 'Apa saja aturan turnamen ini?',
    ]);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('API Key AtmoRouter belum dikonfigurasi')
        ->and($content)->toContain('data: [DONE]');
});

test('endpoint streaming ai memvalidasi keberadaan pesan', function () {
    $response = $this->postJson(route('tournaments.ai-chat-stream', $this->tournament->id), [
        'message' => '',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('message');
});

test('guest menerima pesan peringatan dan token_limit_reached ketika kuota token habis', function () {
    $guestKey = 'ai_chat_guest_tokens_'.md5('127.0.0.1_'.$this->tournament->id);
    Cache::put($guestKey, 3500, now()->addHours(1));

    $response = $this->post(route('tournaments.ai-chat-stream', $this->tournament->id), [
        'message' => 'Tolong jelaskan hadiah turnamen',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('Kamu telah melewati batas chat dengan AI nya')
        ->and($content)->toContain('token_limit_reached')
        ->and($content)->toContain('data: [DONE]');
});

test('guest dapat mereset kuota chat ai melalui endpoint reset', function () {
    $guestKey = 'ai_chat_guest_tokens_'.md5('127.0.0.1_'.$this->tournament->id);
    Cache::put($guestKey, 3500, now()->addHours(1));

    $response = $this->postJson(route('tournaments.ai-chat-reset', $this->tournament->id));

    $response->assertOk();
    $response->assertJson(['success' => true]);

    expect(Cache::get($guestKey, 0))->toBe(0);
});

test('guest dapat meringkas percakapan melalui endpoint compact untuk mengingat konteks', function () {
    Http::fake([
        'https://atmorouter.dev/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl-compact-123',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Roster Phoenix Esports telah diverifikasi memenuhi syarat format 5v5 Valorant.',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'total_tokens' => 50,
            ],
        ], 200),
    ]);

    Config::set('services.atmorouter.api_key', 'ar-test-key');

    $guestKey = 'ai_chat_guest_tokens_'.md5('127.0.0.1_'.$this->tournament->id);
    Cache::put($guestKey, 3500, now()->addHours(1));

    $response = $this->postJson(route('tournaments.ai-chat-compact', $this->tournament->id), [
        'history' => [
            ['role' => 'user', 'content' => 'Tolong cek roster tim saya Phoenix Esports'],
            ['role' => 'assistant', 'content' => 'Format roster sudah sesuai 5 pemain.'],
        ],
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'compacted_context' => 'Roster Phoenix Esports telah diverifikasi memenuhi syarat format 5v5 Valorant.',
    ]);

    // Memastikan token counter ter-reset setelah compact
    expect(Cache::get($guestKey, 0))->toBe(0);
});
