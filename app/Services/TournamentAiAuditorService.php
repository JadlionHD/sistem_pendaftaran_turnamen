<?php

namespace App\Services;

use App\Models\TournamentRegistration;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TournamentAiAuditorService
{
    public function __construct(
        protected ?string $apiKey = null,
        protected string $baseUrl = 'https://atmorouter.dev/v1',
        protected string $model = 'atmo/deepseek-v4.1-flash',
    ) {
        $this->apiKey = $this->apiKey ?: (string) config('services.atmorouter.api_key');
        $this->baseUrl = (string) config('services.atmorouter.base_url', 'https://atmorouter.dev/v1');
        $this->model = (string) config('services.atmorouter.model', 'atmo/deepseek-v4.1-flash');
    }

    /**
     * Audit a tournament registration using AtmoRouter AI.
     *
     * @return array{
     *     success: true,
     *     message: string,
     *     data: array{
     *         status: string,
     *         score: int,
     *         summary: string,
     *         checklist: array<int, array{rule: string, passed: bool, note: string}>,
     *         recommendation: string,
     *         cost: ?string,
     *         tokens_used: ?int
     *     }
     * }|array{
     *     success: false,
     *     message: string
     * }
     */
    public function audit(TournamentRegistration $registration): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'API Key AtmoRouter belum dikonfigurasi di file .env (ATMOROUTER_API_KEY).',
            ];
        }

        $registration->loadMissing(['tournament.game']);
        $tournament = $registration->tournament;

        // Ambil data tim lain pada turnamen yang sama untuk deteksi potensi duplikasi pemain
        $otherTeams = TournamentRegistration::query()
            ->where('tournament_id', $tournament->id)
            ->where('id', '!=', $registration->id)
            ->get(['team_name', 'team_members', 'captain_name']);

        $otherTeamsSummary = $otherTeams->map(function (TournamentRegistration $other): string {
            return sprintf(
                '- Tim: %s (Kapten: %s), Anggota: %s',
                $other->team_name,
                $other->captain_name,
                $other->team_members
            );
        })->implode("\n");

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($registration, $tournament, $otherTeamsSummary);

        try {
            /** @var Response $response */
            $response = Http::withToken($this->apiKey)
                ->timeout(45)
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                    'model' => $this->model,
                    'reasoning_effort' => (string) config('services.atmorouter.reasoning_effort', 'medium'),
                    'max_tokens' => 2500,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => $userPrompt,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('AtmoRouter API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Gagal menghubungi AtmoRouter API (Status '.$response->status().'): '.$response->json('error.message', 'Terjadi kesalahan pada layanan AI.'),
                ];
            }

            $content = (string) $response->json('choices.0.message.content');
            $cost = $response->header('X-Atmorouter-Cost');
            $tokensUsed = $response->json('usage.total_tokens');

            $parsedData = $this->parseJsonResponse($content);

            if (! $parsedData) {
                Log::warning('AtmoRouter response was not valid JSON', ['content' => $content]);

                return [
                    'success' => false,
                    'message' => 'Respon dari AI tidak dalam format JSON yang valid.',
                ];
            }

            $aiStatus = in_array($parsedData['status'] ?? '', ['passed', 'flagged', 'rejected'], true)
                ? $parsedData['status']
                : 'flagged';
            $aiScore = isset($parsedData['score']) ? max(0, min(100, (int) $parsedData['score'])) : 50;
            $aiSummary = (string) ($parsedData['summary'] ?? 'Audit selesai.');
            $aiChecklist = is_array($parsedData['checklist'] ?? null) ? $parsedData['checklist'] : [];
            $aiRecommendation = (string) ($parsedData['recommendation'] ?? 'Tinjau manual.');

            $registration->update([
                'ai_status' => $aiStatus,
                'ai_score' => $aiScore,
                'ai_summary' => $aiSummary,
                'ai_checklist' => $aiChecklist,
                'ai_recommendation' => $aiRecommendation,
                'ai_cost' => $cost,
                'ai_tokens_used' => $tokensUsed ? (int) $tokensUsed : null,
                'ai_checked_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Audit pendaftaran tim oleh AI berhasil diselesaikan.',
                'data' => [
                    'status' => $aiStatus,
                    'score' => $aiScore,
                    'summary' => $aiSummary,
                    'checklist' => $aiChecklist,
                    'recommendation' => $aiRecommendation,
                    'cost' => $cost,
                    'tokens_used' => $tokensUsed,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Exception during AI Audit', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat memproses audit AI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Build the system prompt.
     */
    protected function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah AI Auditor Turnamen Esports profesional dan teliti.
Tugasmu adalah menganalisis data pendaftaran tim dan memverifikasi kelayakannya berdasarkan data turnamen dan aturan game.

Periksa 4 pilar utama:
1. Kelengkapan Roster: Periksa apakah anggota tim lengkap, format Nickname/IGN dan ID Akun Game terisi dengan jelas dan sesuai standar game.
2. Pencegahan Multi-Tim / Joki: Periksa apakah ada nama pemain / ID Game yang terdaftar ganda di tim lain pada turnamen yang sama.
3. Etika & Kesopanan: Pastikan nama tim, nama kapten, dan nickname pemain bersih dari unsur kata kotor, hinaan, SARA, atau pornografi.
4. Kesesuaian Regulasi: Bandingkan data tim dengan aturan khusus turnamen yang terlampir.

KRITERIA STATUS:
- "passed": Data lengkap, format ID game valid, tidak ada duplikasi tim lain, nama sopan (score 80-100).
- "flagged": Format ada yang kurang lengkap/ambigu, atau nama agak beresiko, atau butuh konfirmasi panitia (score 50-79).
- "rejected": Pelanggaran berat seperti nama kasar/SARA, duplikasi pemain jelas terdeteksi di tim lain, atau data fiktif (score 0-49).

PENTING:
Keluarkan jawaban HANYA berupa teks JSON valid tanpa format markdown (tanpa ```json ... ```).
Skema JSON yang harus dipatuhi:
{
  "status": "passed" | "flagged" | "rejected",
  "score": 85,
  "summary": "Ringkasan analisis dalam 1-2 kalimat bahasa Indonesia.",
  "checklist": [
    {"rule": "Kelengkapan Roster", "passed": true, "note": "Keterangan singkat"},
    {"rule": "Format ID Akun Game", "passed": true, "note": "Keterangan singkat"},
    {"rule": "Pengecekan Duplikasi", "passed": true, "note": "Keterangan singkat"},
    {"rule": "Etika & Kesopanan Nama", "passed": true, "note": "Keterangan singkat"}
  ],
  "recommendation": "Rekomendasi spesifik untuk panitia, misal: 'Setujui pendaftaran', 'Minta peserta melengkapi ID server', atau 'Tolak pendaftaran'."
}
PROMPT;
    }

    /**
     * Build the user prompt payload.
     */
    protected function buildUserPrompt(
        TournamentRegistration $registration,
        mixed $tournament,
        string $otherTeamsSummary
    ): string {
        $gameName = $tournament->game->name ?? 'Esports Umum';
        $rules = $tournament->rules ?: 'Ikuti aturan standar fair play esports.';
        $otherTeamsText = $otherTeamsSummary ?: 'Belum ada tim lain yang terdaftar.';

        return <<<TEXT
=== DATA TURNAMEN ===
Judul Turnamen: {$tournament->title}
Game: {$gameName}
Deskripsi: {$tournament->description}
Aturan Khusus Turnamen:
{$rules}
Batas Registrasi: {$tournament->registration_deadline?->format('d M Y')}
Biaya Registrasi: Rp {$tournament->registration_fee}

=== DATA PENDAFTARAN TIM YANG SEDANG DIAUDIT ===
ID Pendaftaran: {$registration->id}
Nama Tim: {$registration->team_name}
Nama Kapten: {$registration->captain_name}
Kontak Kapten: WhatsApp: {$registration->captain_whatsapp}, Email: {$registration->captain_email}
Data Roster & Anggota:
{$registration->team_members}

=== DAFTAR TIM LAIN YANG SUDAH TERDAFTAR (UNTUK CEK DUPLIKASI) ===
{$otherTeamsText}

Silakan audit pendaftaran tim ini dan berikan output dalam format JSON sesuai instruksi.
TEXT;
    }

    /**
     * Clean and parse JSON response from the LLM.
     *
     * @return array<string, mixed>|null
     */
    protected function parseJsonResponse(string $rawContent): ?array
    {
        $cleaned = trim($rawContent);

        // Hapus kode blok markdown ```json ... ``` jika model menyertakannya
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $cleaned, $matches)) {
            $cleaned = trim($matches[1]);
        }

        // Cari kurung kurawal pertama dan terakhir jika ada teks pendahuluan/penutup
        $firstBrace = strpos($cleaned, '{');
        $lastBrace = strrpos($cleaned, '}');

        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $cleaned = substr($cleaned, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        $decoded = json_decode($cleaned, true);

        return is_array($decoded) ? $decoded : null;
    }
}
