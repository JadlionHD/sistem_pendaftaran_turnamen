<?php

namespace App\Http\Requests;

use App\Models\Tournament;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTournamentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Tournament|null $tournament */
        $tournament = $this->route('tournament');

        return $tournament ? $this->user()?->can('update', $tournament) : false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'game_id' => ['required', 'integer', 'exists:games,id'],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['required', 'string', 'min:10'],
            'rules' => ['nullable', 'string'],
            'max_teams' => ['required', 'integer', 'min:2', 'max:128'],
            'registration_fee' => ['required', 'integer', 'min:0'],
            'prize_pool' => ['nullable', 'string', 'max:100'],
            'registration_deadline' => ['required', 'date'],
            'start_date' => ['required', 'date', 'after_or_equal:registration_deadline'],
            'status' => ['required', 'string', 'in:draft,open,closed,ongoing,completed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'game_id.required' => 'Pilihan game wajib dipilih.',
            'game_id.exists' => 'Game yang dipilih tidak valid atau tidak ditemukan.',
            'title.required' => 'Judul turnamen wajib diisi.',
            'title.min' => 'Judul turnamen minimal harus :min karakter.',
            'title.max' => 'Judul turnamen maksimal :max karakter.',
            'description.required' => 'Deskripsi turnamen wajib diisi.',
            'description.min' => 'Deskripsi turnamen minimal harus :min karakter.',
            'max_teams.required' => 'Batas jumlah tim wajib diisi.',
            'max_teams.min' => 'Batas jumlah tim minimal :min tim.',
            'max_teams.max' => 'Batas jumlah tim maksimal :max tim.',
            'registration_fee.required' => 'Biaya pendaftaran wajib diisi (isi 0 jika gratis).',
            'registration_fee.min' => 'Biaya pendaftaran tidak boleh bernilai negatif.',
            'registration_deadline.required' => 'Batas waktu pendaftaran wajib diisi.',
            'start_date.required' => 'Tanggal mulai turnamen wajib diisi.',
            'start_date.after_or_equal' => 'Tanggal mulai turnamen harus sama atau setelah batas waktu pendaftaran.',
            'status.in' => 'Status turnamen tidak valid.',
        ];
    }
}
