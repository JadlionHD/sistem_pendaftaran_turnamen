<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    /**
     * Stream universal AI responses for general esports inquiries, tournaments, and roster eligibility (Available for Guests).
     */
    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'tournament_id' => ['nullable', 'integer', 'exists:tournaments,id'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => [
                'required_with:history',
                'string',
                'in:user,assistant',
            ],
            'history.*.content' => [
                'required_with:history',
                'string',
                'max:2500',
            ],
            'compact_context' => ['nullable', 'string', 'max:1500'],
        ]);

        $guestKey = 'ai_chat_universal_tokens_'.md5((string) $request->ip());
        $currentTokens = (int) Cache::get($guestKey, 0);
        $tokenLimit = (int) config('services.atmorouter.guest_token_limit', 3000);

        if ($currentTokens >= $tokenLimit) {
            return response()->stream(
                function (): void {
                    $warning = 'Kamu telah melewati batas chat dengan AI nya, harap melakukan Reset percakapan untuk memulai sesi baru atau gunakan fitur Compact agar AI tetap mengingat percakapan sebelumnya.';
                    $payload = json_encode([
                        'token_limit_reached' => true,
                        'choices' => [
                            [
                                'delta' => [
                                    'content' => $warning,
                                ],
                            ],
                        ],
                    ]);
                    echo "data: {$payload}\n\n";
                    echo "data: [DONE]\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                },
                200,
                [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache, no-transform',
                    'Connection' => 'keep-alive',
                    'X-Accel-Buffering' => 'no',
                ],
            );
        }

        $apiKey = (string) config('services.atmorouter.api_key');
        $baseUrl = (string) config(
            'services.atmorouter.base_url',
            'https://atmorouter.dev/v1',
        );
        $model = (string) config(
            'services.atmorouter.model',
            'atmo/deepseek-v4.1-flash',
        );
        $reasoningEffort = (string) config(
            'services.atmorouter.reasoning_effort',
            'medium',
        );

        // Fetch platform context: supported games and tournaments
        $games = Game::where('is_active', true)->get();
        $gamesSummary = $games->map(function (Game $g): string {
            return "- {$g->name} (Format {$g->team_size} pemain/tim, Genre: {$g->genre}, Platform: {$g->platform})";
        })->implode("\n");

        $tournaments = Tournament::with(['game'])
            ->withCount(['registrations as approved_teams_count' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->latest('id')
            ->take(12)
            ->get();

        $tournamentsSummary = $tournaments->map(function (Tournament $t): string {
            $fee = $t->registration_fee === 0 ? 'Gratis' : 'Rp '.number_format($t->registration_fee, 0, ',', '.');
            $prize = $t->prize_pool ?: 'Piala & Sertifikat';
            $deadline = $t->registration_deadline->format('d M Y');
            $start = $t->start_date->format('d M Y');
            $game = $t->game ? $t->game->name : 'Esports';
            $status = strtoupper($t->status);
            $slots = "{$t->approved_teams_count}/{$t->max_teams} tim";
            $rulesPreview = $t->rules ? Str::limit(str_replace(["\r", "\n"], ' ', $t->rules), 120) : 'Aturan standar berlaku';

            return "- [ID: {$t->id}] \"{$t->title}\" | Game: {$game} | Status: {$status} | Biaya: {$fee} | Hadiah: {$prize} | Slot: {$slots} | Batas Daftar: {$deadline} | Mulai: {$start} | Aturan: {$rulesPreview}";
        })->implode("\n");

        // Specific tournament context if user is currently looking at one
        $currentContext = '';
        if (! empty($validated['tournament_id'])) {
            $currentTournament = Tournament::with(['game'])->find($validated['tournament_id']);
            if ($currentTournament) {
                $cGame = $currentTournament->game ? $currentTournament->game->name : 'Esports';
                $cFee = $currentTournament->registration_fee === 0 ? 'Gratis' : 'Rp '.number_format($currentTournament->registration_fee, 0, ',', '.');
                $cPrize = $currentTournament->prize_pool ?: 'Piala & Sertifikat';
                $cRules = $currentTournament->rules ?: 'Standar fair play esports.';
                $cDeadline = $currentTournament->registration_deadline->format('d M Y');
                $cStart = $currentTournament->start_date->format('d M Y');
                $currentContext = <<<CONTEXT
KONTEKS HALAMAN SAAT INI:
Pengguna sedang membuka halaman turnamen:
- Judul: "{$currentTournament->title}" (ID: {$currentTournament->id})
- Game: {$cGame}
- Biaya Pendaftaran: {$cFee}
- Total Hadiah: {$cPrize}
- Batas Pendaftaran: {$cDeadline}
- Tanggal Mulai: {$cStart}
- Deskripsi: {$currentTournament->description}
- Regulasi Lengkap:
{$cRules}
Jika pengguna menanyakan "turnamen ini", jawab dengan merujuk pada turnamen tersebut.
CONTEXT;
            }
        }

        $systemPrompt = <<<PROMPT
Kamu adalah AI Assistant Resmi Universal untuk Platform "Sistem Pendaftaran Turnamen Game".
Kamu bertugas membantu pengunjung umum (guest), calon peserta, dan pemain dalam ekosistem turnamen esports di platform ini.

INFORMASI GAME YANG DIDUKUNG:
{$gamesSummary}

DAFTAR TURNAMEN TERBARU & AKTIF DI PLATFORM:
{$tournamentsSummary}

{$currentContext}

TUGAS UTAMA:
1. Menjawab pertanyaan universal seputar turnamen apa saja yang tersedia, jadwal, status pendaftaran, prize pool, dan biaya registrasi.
2. Membantu pengguna melakukan evaluasi kelayakan roster tim (Roster Pre-Check). Jika pengguna mencantumkan nama pemain/tim/ID game, periksa kesesuaian format ID, jumlah pemain inti/cadangan, dan aturan main sesuai game yang dimaksud.
3. Menjelaskan alur pendaftaran turnamen: pilih turnamen di katalog -> klik "Daftarkan Tim" -> isi nama tim, kontak kapten (WhatsApp & Email), dan daftar anggota tim -> kirim pendaftaran untuk diverifikasi admin.
4. Menjelaskan regulasi atau aturan turnamen secara rinci jika ditanyakan.

PANDUAN BERPIKIR & MENJAWAB:
- Lakukan proses reasoning / thinking analitis yang teliti sebelum menyusun jawaban.
- Berikan respon yang ramah, sopan, antusias, terstruktur, dan profesional dalam Bahasa Indonesia.
- Gunakan format markdown (bullet points, bold, headings) agar mudah dan nyaman dibaca.
- Jawab secara to-the-point dan relevan.
- JANGAN MENJAWAB HAL DI LUAR KONTEKS GAME, ESPORTS, DAN SISTEM TURNAMEN INI.
PROMPT;

        if (! empty($validated['compact_context'])) {
            $systemPrompt .= "\n\nMEMORI KONTEKS PERCAKAPAN SEBELUMNYA:\n{$validated['compact_context']}\nHarap ingat dan gunakan informasi di atas saat menjawab pengguna.";
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        if (! empty($validated['history'])) {
            foreach ($validated['history'] as $hist) {
                $messages[] = [
                    'role' => $hist['role'],
                    'content' => $hist['content'],
                ];
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $validated['message'],
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
            'reasoning_effort' => $reasoningEffort,
            'max_tokens' => 2500,
        ];

        $estimatedTurn = (int) ceil((mb_strlen($validated['message']) + 500) * 0.7);
        Cache::put(
            $guestKey,
            $currentTokens + $estimatedTurn,
            now()->addHours(3),
        );

        return response()->stream(
            function () use (
                $apiKey,
                $baseUrl,
                $payload,
                $guestKey,
                $currentTokens,
            ): void {
                if (empty($apiKey)) {
                    $err = json_encode([
                        'choices' => [
                            [
                                'delta' => [
                                    'content' => 'API Key AtmoRouter belum dikonfigurasi di server backend (.env). Silakan hubungi administrator sistem.',
                                ],
                            ],
                        ],
                    ]);
                    echo "data: {$err}\n\n";
                    echo "data: [DONE]\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    return;
                }

                $ch = curl_init(rtrim($baseUrl, '/').'/chat/completions');
                if ($ch === false) {
                    return;
                }

                $jsonPayload = json_encode($payload);
                if ($jsonPayload === false) {
                    return;
                }

                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer '.$apiKey,
                    'Content-Type: application/json',
                    'Accept: text/event-stream',
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function (
                    $ch,
                    string $data,
                ) use ($guestKey, $currentTokens): int {
                    echo $data;
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    if (
                        preg_match(
                            '/"total_tokens"\s*:\s*(\d+)/',
                            $data,
                            $matches,
                        )
                    ) {
                        $exactTokens = (int) $matches[1];
                        Cache::put(
                            $guestKey,
                            $currentTokens + $exactTokens,
                            now()->addHours(3),
                        );
                    }

                    return strlen($data);
                });

                curl_exec($ch);

                if (curl_errno($ch)) {
                    $errText = 'Terjadi gangguan koneksi ke layanan AI: '.curl_error($ch);
                    $errPayload = json_encode([
                        'choices' => [
                            [
                                'delta' => [
                                    'content' => "\n\n{$errText}",
                                ],
                            ],
                        ],
                    ]);
                    echo "data: {$errPayload}\n\n";
                    echo "data: [DONE]\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                curl_close($ch);
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-transform',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /**
     * Reset guest universal chat session and token counter.
     */
    public function reset(Request $request): JsonResponse
    {
        $guestKey = 'ai_chat_universal_tokens_'.md5((string) $request->ip());
        Cache::forget($guestKey);

        return response()->json([
            'success' => true,
            'message' => 'Sesi chat AI telah berhasil di-reset.',
        ]);
    }

    /**
     * Compact conversation history into a concise context summary to save tokens.
     */
    public function compact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'history' => ['required', 'array', 'min:1', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2500'],
            'previous_compact' => ['nullable', 'string', 'max:1000'],
        ]);

        $apiKey = (string) config('services.atmorouter.api_key');
        $baseUrl = (string) config(
            'services.atmorouter.base_url',
            'https://atmorouter.dev/v1',
        );
        $model = (string) config(
            'services.atmorouter.model',
            'atmo/deepseek-v4.1-flash',
        );

        $historyText = collect($validated['history'])
            ->map(function (array $msg): string {
                $role = $msg['role'] === 'user' ? 'User' : 'AI';

                return "{$role}: {$msg['content']}";
            })
            ->implode("\n\n");

        if (! empty($validated['previous_compact'])) {
            $historyText = "Konteks Lama: {$validated['previous_compact']}\n\nPercakapan Terbaru:\n{$historyText}";
        }

        $compacted = '';

        if (! empty($apiKey)) {
            try {
                $response = Http::withToken($apiKey)
                    ->timeout(30)
                    ->post(rtrim($baseUrl, '/').'/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Kamu adalah asisten perangkum memori percakapan asisten turnamen. Tugasmu adalah meringkas percakapan sebelumnya menjadi ringkasan padat dan informatif (maksimal 3-4 kalimat). Cantumkan judul turnamen yang dibahas, susunan tim/roster/ID jika ada, serta pertanyaan penting agar konteks ini bisa diingat pada percakapan berikutnya. Jawab langsung dengan ringkasan tanpa pembuka atau penutup.',
                            ],
                            [
                                'role' => 'user',
                                'content' => $historyText,
                            ],
                        ],
                        'temperature' => 0.3,
                        'max_tokens' => 300,
                    ]);

                if ($response->successful()) {
                    $compacted = (string) $response->json(
                        'choices.0.message.content',
                    );
                }
            } catch (\Throwable $e) {
                // Fallback below
            }
        }

        if (empty($compacted)) {
            $userQuestions = collect($validated['history'])
                ->where('role', 'user')
                ->pluck('content')
                ->map(fn (string $c): string => Str::limit($c, 60))
                ->implode('; ');
            $compacted = "Membahas sistem turnamen & pertanyaan: {$userQuestions}";
        }

        // Reset guest token usage counter when compaction is performed
        $guestKey = 'ai_chat_universal_tokens_'.md5((string) $request->ip());
        Cache::forget($guestKey);

        return response()->json([
            'success' => true,
            'compacted_context' => trim($compacted),
            'message' => 'Percakapan berhasil diringkas dan sesi chat telah diperbarui.',
        ]);
    }
}
