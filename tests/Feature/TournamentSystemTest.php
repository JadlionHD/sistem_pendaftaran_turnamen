<?php

use App\Models\Game;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Database\Seeders\TournamentSeeder;

beforeEach(function () {
    $this->withoutVite();

    $this->admin = User::factory()->create([
        'name' => 'Admin Tournament',
        'email' => 'admin@test.com',
        'is_admin' => true,
    ]);

    $this->user = User::factory()->create([
        'name' => 'Peserta Satu',
        'email' => 'peserta@test.com',
        'is_admin' => false,
    ]);

    $this->game = Game::factory()->create([
        'name' => 'Mobile Legends: Bang Bang',
        'slug' => 'mobile-legends-bang-bang',
        'genre' => 'MOBA',
        'team_size' => 5,
        'platform' => 'Mobile',
        'is_active' => true,
    ]);
});

// TC-01: Login dan logout
test('TC-01: login valid berhasil, login salah gagal, dan logout memutus akses terproteksi', function () {
    // 1. Login valid
    $response = $this->post('/login', [
        'email' => 'peserta@test.com',
        'password' => 'password',
    ]);
    $this->assertAuthenticatedAs($this->user);

    // 2. Logout
    $this->post('/logout');
    $this->assertGuest();

    // 3. Login salah menampilkan error validasi session
    $failResponse = $this->post('/login', [
        'email' => 'peserta@test.com',
        'password' => 'wrong-password',
    ]);
    $failResponse->assertSessionHasErrors('email');
    $this->assertGuest();

    // 4. Akses protected route tanpa login ditolak / diredirect ke login
    $protectedResponse = $this->get('/registrations');
    $protectedResponse->assertRedirect('/login');
});

// TC-02: Daftar dan empty state
test('TC-02: daftar menampilkan data turnamen dan menampilkan empty state jika tanpa data', function () {
    // Kondisi ada data
    $tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
        'title' => 'MLBB Championship 2026',
        'status' => 'open',
    ]);

    $response = $this->get('/tournaments');
    $response->assertOk();
    $response->assertSee('MLBB Championship 2026');

    // Kondisi tanpa data (filter yang tidak ada hasilnya)
    $emptyResponse = $this->get('/tournaments?game_id=99999');
    $emptyResponse->assertOk();
    $emptyResponse->assertInertia(fn ($page) => $page
        ->component('tournaments/Index')
        ->where('tournaments.total', 0)
    );
});

// TC-03: Tambah valid (Pendaftaran tim turnamen)
test('TC-03: pendaftaran tim valid berhasil tersimpan dengan relasi benar dan persisten', function () {
    $tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
        'status' => 'open',
        'max_teams' => 16,
        'registration_deadline' => now()->addDays(5)->toDateString(),
    ]);

    $payload = [
        'tournament_id' => $tournament->id,
        'team_name' => 'Garuda Esports',
        'captain_name' => 'Andi Pratama',
        'captain_whatsapp' => '081234567890',
        'captain_email' => 'andi@garuda.com',
        'team_members' => "1. PlayerOne (Kapten)\n2. PlayerTwo\n3. PlayerThree\n4. PlayerFour\n5. PlayerFive",
    ];

    $response = $this->actingAs($this->user)
        ->post('/registrations', $payload);

    $response->assertRedirect('/registrations');
    $response->assertSessionHas('success');

    // Pastikan data tersimpan di DB dengan relasi yang tepat
    $this->assertDatabaseHas('tournament_registrations', [
        'tournament_id' => $tournament->id,
        'user_id' => $this->user->id,
        'team_name' => 'Garuda Esports',
        'status' => 'pending',
    ]);
});

// TC-04: Ubah valid (Pre-fill nilai lama & simpan perubahan)
test('TC-04: form ubah memuat data lama dan perubahan valid tersimpan tanpa merusak relasi', function () {
    $tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
        'status' => 'open',
    ]);

    $registration = TournamentRegistration::factory()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $this->user->id,
        'team_name' => 'Old Team Name',
        'captain_name' => 'Old Captain',
        'captain_whatsapp' => '08111111111',
        'captain_email' => 'old@team.com',
        'team_members' => 'Member A, Member B, Member C',
        'status' => 'pending',
    ]);

    // Buka halaman edit
    $editPage = $this->actingAs($this->user)
        ->get("/registrations/{$registration->id}/edit");
    $editPage->assertOk();
    $editPage->assertInertia(fn ($page) => $page
        ->component('registrations/Edit')
        ->where('registration.team_name', 'Old Team Name')
    );

    // Simpan perubahan valid
    $updateResponse = $this->actingAs($this->user)
        ->put("/registrations/{$registration->id}", [
            'team_name' => 'New Team Name Reborn',
            'captain_name' => 'New Captain Name',
            'captain_whatsapp' => '08999999999',
            'captain_email' => 'new@team.com',
            'team_members' => 'Member X, Member Y, Member Z',
        ]);

    $updateResponse->assertRedirect('/registrations');
    $updateResponse->assertSessionHas('success');

    // Record terupdate dan relasi tournament_id & user_id tetap utuh
    $registration->refresh();
    expect($registration->team_name)->toBe('New Team Name Reborn');
    expect($registration->tournament_id)->toBe($tournament->id);
    expect($registration->user_id)->toBe($this->user->id);
});

// TC-05: Input kosong / whitespace ditolak server
test('TC-05: input kosong atau hanya spasi ditolak server dengan pesan validasi yang jelas', function () {
    $tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
        'status' => 'open',
    ]);

    $response = $this->actingAs($this->user)
        ->post('/registrations', [
            'tournament_id' => $tournament->id,
            'team_name' => '   ',
            'captain_name' => '',
            'captain_whatsapp' => '',
            'captain_email' => 'not-an-email',
            'team_members' => '',
        ]);

    $response->assertSessionHasErrors([
        'team_name',
        'captain_name',
        'captain_whatsapp',
        'captain_email',
        'team_members',
    ]);

    // Data tidak tersimpan di database
    $this->assertDatabaseCount('tournament_registrations', 0);
});

// TC-06: Batas input (Batas kuota tim penuh ditolak)
test('TC-06: turnamen yang kuotanya sudah penuh menolak pendaftaran baru', function () {
    $tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
        'status' => 'open',
        'max_teams' => 2,
    ]);

    // Isi 2 tim yang sudah di-approve sehingga kuota penuh
    TournamentRegistration::factory()->count(2)->create([
        'tournament_id' => $tournament->id,
        'status' => 'approved',
    ]);

    // User mencoba mendaftar ke-3
    $response = $this->actingAs($this->user)
        ->post('/registrations', [
            'tournament_id' => $tournament->id,
            'team_name' => 'Tim Kelewat Kuota',
            'captain_name' => 'Kapten Budi',
            'captain_whatsapp' => '081234567890',
            'captain_email' => 'budi@test.com',
            'team_members' => 'Pemain 1, Pemain 2, Pemain 3, Pemain 4, Pemain 5',
        ]);

    $response->assertSessionHasErrors('tournament_id');
});

// TC-07: Referensi tidak sah (ID relasi tidak ada di database)
test('TC-07: pendaftaran dengan tournament_id yang tidak ada ditolak tanpa membuat record rusak', function () {
    $response = $this->actingAs($this->user)
        ->post('/registrations', [
            'tournament_id' => 999999, // Tidak ada di DB
            'team_name' => 'Tim Valid',
            'captain_name' => 'Kapten Valid',
            'captain_whatsapp' => '081234567890',
            'captain_email' => 'valid@test.com',
            'team_members' => 'Member Satu, Member Dua',
        ]);

    $response->assertSessionHasErrors('tournament_id');
    $this->assertDatabaseCount('tournament_registrations', 0);
});

// TC-08: Akses tanpa login
test('TC-08: panggil endpoint protected langsung tanpa login ditolak dan data tidak berubah', function () {
    $response = $this->post('/registrations', [
        'tournament_id' => 1,
        'team_name' => 'Hacker Team',
        'captain_name' => 'Hacker',
        'captain_whatsapp' => '081234567890',
        'captain_email' => 'hacker@test.com',
        'team_members' => 'Hack 1, Hack 2',
    ]);

    $response->assertRedirect('/login');
    $this->assertDatabaseCount('tournament_registrations', 0);
});

// TC-09: Akses tidak berhak (Otorisasi: Peserta biasa tidak boleh ubah turnamen atau approve tim)
test('TC-09: peserta biasa ditolak (403 Forbidden) saat mencoba mengedit turnamen atau approve pendaftaran', function () {
    $tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
    ]);

    $registration = TournamentRegistration::factory()->create([
        'tournament_id' => $tournament->id,
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);

    // 1. Peserta mencoba update data turnamen milik admin -> 403 Forbidden
    $resp1 = $this->actingAs($this->user)
        ->put("/tournaments/{$tournament->id}", [
            'game_id' => $this->game->id,
            'title' => 'Judul Dibajak Peserta',
            'description' => 'Deskripsi baru yang diubah peserta',
            'max_teams' => 16,
            'registration_fee' => 0,
            'registration_deadline' => now()->addDays(5)->toDateString(),
            'start_date' => now()->addDays(7)->toDateString(),
            'status' => 'open',
        ]);
    $resp1->assertForbidden();

    // 2. Peserta mencoba menyetujui (approve) pendaftarannya sendiri -> 403 Forbidden
    $resp2 = $this->actingAs($this->user)
        ->patch("/registrations/{$registration->id}/status", [
            'status' => 'approved',
        ]);
    $resp2->assertForbidden();

    // Data tetap tidak berubah di database
    expect($registration->fresh()->status)->toBe('pending');
});

// TC-10: ID data tidak ada (404 Not Found)
test('TC-10: detail atau ubah turnamen dengan ID tidak ada ditangani dengan respons 404', function () {
    $response = $this->get('/tournaments/999999');
    $response->assertNotFound();
});

// TC-11: Validasi API negatif (Pencegahan duplikasi nama tim pada turnamen yang sama)
test('TC-11: nama tim duplikat pada turnamen yang sama ditolak dengan pesan kesalahan yang jelas', function () {
    $tournament = Tournament::factory()->create([
        'game_id' => $this->game->id,
        'organizer_id' => $this->admin->id,
        'status' => 'open',
    ]);

    // Tim pertama daftar dengan nama "Black Dragon"
    TournamentRegistration::factory()->create([
        'tournament_id' => $tournament->id,
        'team_name' => 'Black Dragon',
    ]);

    // Tim kedua mencoba mendaftar dengan nama yang sama persis
    $response = $this->actingAs($this->user)
        ->post('/registrations', [
            'tournament_id' => $tournament->id,
            'team_name' => 'Black Dragon',
            'captain_name' => 'Kapten Lain',
            'captain_whatsapp' => '081234567890',
            'captain_email' => 'lain@test.com',
            'team_members' => 'Pemain 1, Pemain 2, Pemain 3, Pemain 4, Pemain 5',
        ]);

    $response->assertSessionHasErrors('team_name');
    $this->assertDatabaseCount('tournament_registrations', 1);
});

// TC-12: Verifikasi instalasi dan seed berjalan lengkap
test('TC-12: seeder berhasil mengisi data game pendukung, turnamen, dan akun demo', function () {
    $this->seed(TournamentSeeder::class);

    $this->assertDatabaseHas('users', ['email' => 'admin@turnamen.test', 'is_admin' => true]);
    $this->assertDatabaseHas('users', ['email' => 'kapten1@turnamen.test', 'is_admin' => false]);
    $this->assertDatabaseHas('games', ['slug' => 'mobile-legends']);
    $this->assertDatabaseHas('tournaments', ['slug' => 'mlbb-campus-championship-2026']);
    $this->assertDatabaseHas('tournament_registrations', ['team_name' => 'Garuda Esports', 'status' => 'approved']);
});
