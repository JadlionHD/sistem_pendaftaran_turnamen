<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TournamentAiChatController extends Controller
{
    /**
     * Stream AI responses for tournament queries and roster eligibility checks (Available for Guests).
     */
    public function stream(Request $request, Tournament $tournament): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'history' => ['nullable', 'array', 'max:8'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2500'],
            'compact_context' => ['nullable', 'string', 'max:1500'],
        ]);

        $guestKey = 'ai_chat_guest_tokens_'.md5((string) $request->ip().'_'.$tournament->id);
        $currentTokens = (int) Cache::get($guestKey, 0);
        $tokenLimit = (int) config('services.atmorouter.guest_token_limit', 3000);

        if ($currentTokens >= $tokenLimit) {
            return response()->stream(function (): void {
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
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-transform',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        $apiKey = (string) config('services.atmorouter.api_key');
        $baseUrl = (string) config('services.atmorouter.base_url', 'https://atmorouter.dev/v1');
        $model = (string) config('services.atmorouter.model', 'atmo/deepseek-v4.1-flash');
        $reasoningEffort = (string) config('services.atmorouter.reasoning_effort', 'medium');

        $tournament->loadMissing('game');
        $gameName = $tournament->game->name ?? 'Esports';
        $rules = $tournament->rules ?: 'Standar fair play esports.';
        $deadline = $tournament->registration_deadline->format('d M Y');
        $start = $tournament->start_date->format('d M Y');
        $fee = $tournament->registration_fee === 0 ? 'Gratis' : 'Rp '.number_format($tournament->registration_fee, 0, ',', '.');
        $prize = $tournament->prize_pool ?: 'Belum ditentukan';
        $slots = "{$tournament->approvedRegistrations()->count()} / {$tournament->max_teams} tim terdaftar";

        $systemPrompt = <<<PROMPT
Kamu adalah AI Agent Resmi untuk turnamen esports: "{$tournament->title}".
Game: {$gameName}
Deskripsi Turnamen: {$tournament->description}
Aturan Khusus Turnamen:
{$rules}
Biaya Pendaftaran: {$fee}
Total Hadiah: {$prize}
Batas Akhir Pendaftaran: {$deadline}
Tanggal Mulai Pertandingan: {$start}
Status Slot: {$slots}

TUGAS UTAMA:
1. Membantu pengunjung/guest menjawab pertanyaan seputar turnamen, aturan, jadwal, dan hadiah.
2. Melakukan evaluasi/pengecekan kelayakan tim (Roster Pre-Check) jika pengunjung memberikan data tim atau nama pemain mereka. Analisis apakah format ID akun game, jumlah anggota, dan role sesuai dengan regulasi game {$gameName}.
3. Menjelaskan langkah-langkah pendaftaran jika ditanya.

PANDUAN BERPIKIR & MENJAWAB:
- Lakukan proses thinking dan penalaran analitis yang teliti sebelum menyusun jawaban.
- Telaah setiap poin aturan turnamen, batasan slot, dan kriteria roster game {$gameName}.
- Berikan respon yang ramah, jelas, antusias, dan profesional dalam Bahasa Indonesia.
- Gunakan format markdown (bullet points, bold) agar mudah dibaca.
- Jawab secara langsung dan to-the-point tanpa bertele-tele.
PROMPT;

        if (! empty($validated['compact_context'])) {
            $systemPrompt .= "\n\nMEMORI KONTEKS PERCAKAPAN SEBELUMNYA:\n{$validated['compact_context']}\nHarap ingat dan gunakan informasi di atas saat menjawab pengguna.";
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

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
        Cache::put($guestKey, $currentTokens + $estimatedTurn, now()->addHours(3));

        return response()->stream(function () use ($apiKey, $baseUrl, $payload, $guestKey, $currentTokens): void {
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
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, string $data) use ($guestKey, $currentTokens): int {
                echo $data;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                if (preg_match('/"total_tokens"\s*:\s*(\d+)/', $data, $matches)) {
                    $exactTokens = (int) $matches[1];
                    Cache::put($guestKey, $currentTokens + $exactTokens, now()->addHours(3));
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
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Reset guest chat session and token counter.
     */
    public function reset(Request $request, Tournament $tournament): JsonResponse
    {
        $guestKey = 'ai_chat_guest_tokens_'.md5((string) $request->ip().'_'.$tournament->id);
        Cache::forget($guestKey);

        return response()->json([
            'success' => true,
            'message' => 'Sesi chat AI telah berhasil di-reset.',
        ]);
    }

    /**
     * Compact conversation history into a concise context summary to save tokens.
     */
    public function compact(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate([
            'history' => ['required', 'array', 'min:1', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2500'],
            'previous_compact' => ['nullable', 'string', 'max:1000'],
        ]);

        $apiKey = (string) config('services.atmorouter.api_key');
        $baseUrl = (string) config('services.atmorouter.base_url', 'https://atmorouter.dev/v1');
        $model = (string) config('services.atmorouter.model', 'atmo/deepseek-v4.1-flash');

        $historyText = collect($validated['history'])->map(function (array $msg): string {
            $role = $msg['role'] === 'user' ? 'User' : 'AI';

            return "{$role}: {$msg['content']}";
        })->implode("\n\n");

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
                                'content' => 'Kamu adalah asisten perangkum memori percakapan turnamen. Tugasmu adalah meringkas percakapan sebelumnya menjadi ringkasan padat dan informatif (maksimal 3-4 kalimat). Cantumkan nama tim, susunan pemain/ID yang telah diperiksa, dan aturan turnamen yang sempat dibahas, agar konteks ini bisa diingat pada percakapan berikutnya. Jawab langsung dengan ringkasan tanpa pembuka atau penutup.',
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
                    $compacted = (string) $response->json('choices.0.message.content');
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
            $compacted = "Membahas turnamen {$tournament->title}: {$userQuestions}";
        }

        // Reset guest token usage counter when compaction is performed
        $guestKey = 'ai_chat_guest_tokens_'.md5((string) $request->ip().'_'.$tournament->id);
        Cache::forget($guestKey);

        return response()->json([
            'success' => true,
            'compacted_context' => trim($compacted),
            'message' => 'Percakapan berhasil diringkas dan sesi chat telah diperbarui.',
        ]);
    }
}
