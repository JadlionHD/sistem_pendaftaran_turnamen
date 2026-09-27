<?php

namespace App\Http\Requests;

use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'team_name' => ['required', 'string', 'min:3', 'max:50'],
            'captain_name' => ['required', 'string', 'min:3', 'max:100'],
            'captain_whatsapp' => ['required', 'string', 'min:9', 'max:20', 'regex:/^[0-9+ -]+$/'],
            'captain_email' => ['required', 'email', 'max:100'],
            'team_members' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tournament_id.required' => 'Turnamen wajib dipilih.',
            'tournament_id.exists' => 'Turnamen yang dipilih tidak valid atau tidak ditemukan.',
            'team_name.required' => 'Nama tim wajib diisi.',
            'team_name.min' => 'Nama tim minimal :min karakter.',
            'team_name.max' => 'Nama tim maksimal :max karakter.',
            'captain_name.required' => 'Nama kapten wajib diisi.',
            'captain_name.min' => 'Nama kapten minimal :min karakter.',
            'captain_whatsapp.required' => 'Nomor WhatsApp kapten wajib diisi.',
            'captain_whatsapp.min' => 'Nomor WhatsApp minimal :min digit.',
            'captain_whatsapp.max' => 'Nomor WhatsApp maksimal :max digit.',
            'captain_whatsapp.regex' => 'Format nomor WhatsApp tidak valid (hanya angka, +, -, atau spasi).',
            'captain_email.required' => 'Email kapten wajib diisi.',
            'captain_email.email' => 'Format email kapten tidak valid.',
            'team_members.required' => 'Daftar nama anggota tim wajib diisi.',
            'team_members.min' => 'Daftar anggota tim minimal :min karakter.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $tournamentId = $this->input('tournament_id');
            if (! $tournamentId) {
                return;
            }

            /** @var Tournament|null $tournament */
            $tournament = Tournament::find($tournamentId);
            if (! $tournament) {
                return;
            }

            // Validasi apakah turnamen masih buka dan kuota belum penuh
            if ($tournament->status !== 'open') {
                $validator->errors()->add('tournament_id', 'Pendaftaran untuk turnamen ini sudah ditutup.');
            }

            if (now()->startOfDay()->gt($tournament->registration_deadline)) {
                $validator->errors()->add('tournament_id', 'Batas waktu pendaftaran untuk turnamen ini telah berakhir.');
            }

            if ($tournament->isFull()) {
                $validator->errors()->add('tournament_id', 'Kuota slot pendaftaran turnamen ini sudah penuh.');
            }

            // Cek nama tim unik di turnamen ini
            $teamName = trim((string) $this->input('team_name'));
            if ($teamName !== '') {
                $alreadyExists = TournamentRegistration::where('tournament_id', $tournament->id)
                    ->whereRaw('LOWER(team_name) = ?', [strtolower($teamName)])
                    ->exists();

                if ($alreadyExists) {
                    $validator->errors()->add('team_name', 'Nama tim ini sudah terdaftar pada turnamen yang dipilih.');
                }
            }
        });
    }
}
