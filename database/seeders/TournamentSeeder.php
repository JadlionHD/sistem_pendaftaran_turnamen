<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TournamentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Akun Demo
        $admin = User::firstOrCreate(
            ['email' => 'admin@turnamen.test'],
            [
                'name' => 'Admin Panitia',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $kapten1 = User::firstOrCreate(
            ['email' => 'kapten1@turnamen.test'],
            [
                'name' => 'Andi Pratama (Kapten Garuda)',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        $kapten2 = User::firstOrCreate(
            ['email' => 'kapten2@turnamen.test'],
            [
                'name' => 'Budi Santoso (Kapten Evos Junior)',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        // 2. Data Master Game (Data Pendukung Domain)
        $gamesData = [
            [
                'name' => 'Mobile Legends: Bang Bang',
                'slug' => 'mobile-legends',
                'genre' => 'MOBA',
                'team_size' => 5,
                'platform' => 'Mobile',
                'description' => 'Game MOBA 5v5 mobile terpopuler di Asia Tenggara.',
                'is_active' => true,
            ],
            [
                'name' => 'Valorant',
                'slug' => 'valorant',
                'genre' => 'Tactical FPS',
                'team_size' => 5,
                'platform' => 'PC',
                'description' => 'Penembak taktis orang pertama berbasis karakter 5v5.',
                'is_active' => true,
            ],
            [
                'name' => 'PUBG Mobile',
                'slug' => 'pubg-mobile',
                'genre' => 'Battle Royale',
                'team_size' => 4,
                'platform' => 'Mobile',
                'description' => 'Pertarungan bertahan hidup 100 pemain untuk gelar Winner Winner Chicken Dinner.',
                'is_active' => true,
            ],
            [
                'name' => 'Free Fire',
                'slug' => 'free-fire',
                'genre' => 'Battle Royale',
                'team_size' => 4,
                'platform' => 'Mobile',
                'description' => 'Game survival shooter berdurasi 10 menit dengan aksi intens.',
                'is_active' => true,
            ],
            [
                'name' => 'Dota 2',
                'slug' => 'dota-2',
                'genre' => 'MOBA',
                'team_size' => 5,
                'platform' => 'PC',
                'description' => 'Game strategi MOBA legendaris dengan kompetisi kelas dunia.',
                'is_active' => true,
            ],
        ];

        $games = [];
        foreach ($gamesData as $data) {
            $games[$data['slug']] = Game::updateOrCreate(['slug' => $data['slug']], $data);
        }

        // 3. Modul Utama: Data Turnamen
        $mlbbTourney = Tournament::updateOrCreate(
            ['slug' => 'mlbb-campus-championship-2026'],
            [
                'game_id' => $games['mobile-legends']->id,
                'organizer_id' => $admin->id,
                'title' => 'MLBB Campus Championship 2026',
                'description' => 'Turnamen Mobile Legends tingkat mahasiswa berskala nasional dengan sistem double elimination. Tunjukkan kemampuan terbaik tim kampusmu!',
                'rules' => "1. Tim terdiri dari 5 pemain utama dan 1 cadangan.\n2. Seluruh pemain dilarang menggunakan emulator.\n3. Wajib sportivitas tinggi, toxic/cheat akan langsung didiskualifikasi.\n4. Skin bebas, all tier welcome.",
                'max_teams' => 16,
                'registration_fee' => 50000,
                'prize_pool' => 'Rp 5.000.000',
                'registration_deadline' => now()->addDays(7)->toDateString(),
                'start_date' => now()->addDays(10)->toDateString(),
                'status' => 'open',
            ]
        );

        $valTourney = Tournament::updateOrCreate(
            ['slug' => 'valorant-radiant-cup-season-2'],
            [
                'game_id' => $games['valorant']->id,
                'organizer_id' => $admin->id,
                'title' => 'Valorant Radiant Cup Season 2',
                'description' => 'Kompetisi Valorant 5v5 mode standar. Terbuka untuk seluruh tim amatir dan semi-pro.',
                'rules' => "1. Mode kompetitif standar Custom Match.\n2. Map pool: Ascent, Bind, Haven, Split, Sunset.\n3. Overtime: Win by 2.\n4. Ping server Jakarta maks 60ms.",
                'max_teams' => 16,
                'registration_fee' => 0,
                'prize_pool' => 'Rp 3.500.000',
                'registration_deadline' => now()->addDays(5)->toDateString(),
                'start_date' => now()->addDays(8)->toDateString(),
                'status' => 'open',
            ]
        );

        $pubgTourney = Tournament::updateOrCreate(
            ['slug' => 'pubg-mobile-national-war-2026'],
            [
                'game_id' => $games['pubg-mobile']->id,
                'organizer_id' => $admin->id,
                'title' => 'PUBG Mobile National War 2026',
                'description' => 'Turnamen PUBG Mobile 16 tim per grup dengan total 4 round: Erangel, Miramar, Sanhok, Erangel.',
                'rules' => "1. Device smartphone only (no tablet/iPad/emulator).\n2. Room format TPP Squad.\n3. Point system mengacu pada regulasi PMGC resmi.",
                'max_teams' => 16,
                'registration_fee' => 25000,
                'prize_pool' => 'Rp 2.500.000',
                'registration_deadline' => now()->addDays(12)->toDateString(),
                'start_date' => now()->addDays(15)->toDateString(),
                'status' => 'open',
            ]
        );

        Tournament::updateOrCreate(
            ['slug' => 'dota-2-ancient-clash-archive'],
            [
                'game_id' => $games['dota-2']->id,
                'organizer_id' => $admin->id,
                'title' => 'Dota 2 Ancient Clash (Selesai)',
                'description' => 'Turnamen Dota 2 yang telah selesai diselenggarakan pada periode sebelumnya.',
                'rules' => 'Format Captains Mode 5v5.',
                'max_teams' => 8,
                'registration_fee' => 0,
                'prize_pool' => 'Rp 1.500.000',
                'registration_deadline' => now()->subDays(10)->toDateString(),
                'start_date' => now()->subDays(7)->toDateString(),
                'status' => 'completed',
            ]
        );

        // 4. Modul Pendaftaran Turnamen
        TournamentRegistration::updateOrCreate(
            [
                'tournament_id' => $mlbbTourney->id,
                'team_name' => 'Garuda Esports',
            ],
            [
                'user_id' => $kapten1->id,
                'captain_name' => 'Andi Pratama',
                'captain_whatsapp' => '081234567890',
                'captain_email' => 'kapten1@turnamen.test',
                'team_members' => "1. Andi 'GarudaOne' Pratama (Jungler / Kapten)\n2. Dimas 'GarudaTwo' Kurnia (Roamer)\n3. Rian 'GarudaThree' Putra (Midlaner)\n4. Fajar 'GarudaFour' Ramadhan (Goldlaner)\n5. Gilang 'GarudaFive' Saputra (EXP Laner)",
                'status' => 'approved',
                'admin_notes' => 'Pembayaran dan data pemain terverifikasi lengkap.',
            ]
        );

        TournamentRegistration::updateOrCreate(
            [
                'tournament_id' => $mlbbTourney->id,
                'team_name' => 'Evos Junior Reborn',
            ],
            [
                'user_id' => $kapten2->id,
                'captain_name' => 'Budi Santoso',
                'captain_whatsapp' => '082198765432',
                'captain_email' => 'kapten2@turnamen.test',
                'team_members' => "1. Budi Santoso (Kapten)\n2. Eko Saputro\n3. Doni Alamsyah\n4. Wahyu Hidayat\n5. Rizky Pratama",
                'status' => 'pending',
                'admin_notes' => null,
            ]
        );

        TournamentRegistration::updateOrCreate(
            [
                'tournament_id' => $valTourney->id,
                'team_name' => 'Phantom Vipers',
            ],
            [
                'user_id' => $kapten1->id,
                'captain_name' => 'Andi Pratama',
                'captain_whatsapp' => '081234567890',
                'captain_email' => 'kapten1@turnamen.test',
                'team_members' => "1. Andi Pratama (Duelist / Jett)\n2. Yoga Firmansyah (Initiator / Sova)\n3. Kevin Julio (Controller / Omen)\n4. Randy Pangalila (Sentinel / Killjoy)\n5. Arya Saloka (Flex / KAY/O)",
                'status' => 'pending',
                'admin_notes' => null,
            ]
        );
    }
}
