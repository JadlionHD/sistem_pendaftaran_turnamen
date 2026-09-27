# 📋 Panduan Pengujian & Laporan Hasil Uji
## Sistem Pendaftaran Turnamen Game (E-Sport Tournament Hub)
**Mata Kuliah**: Pemrograman Web 2 / Praktikum Web — Evaluasi UTS Mini Project  
**Status Pengujian**: ✅ **100% LULUS (ALL TESTS PASSED)**

---

## 📌 1. Ikhtisar Pengujian

Pengujian pada sistem ini dirancang untuk memastikan keandalan fungsional (*functional reliability*), keamanan data (*data security & authorization*), serta integritas aturan bisnis (*business logic & constraint enforcement*) pada seluruh alur pendaftaran turnamen e-sport.

Pengujian dilakukan melalui dua pendekatan komprehensif:
1. **Automated Feature & Integration Testing (Pest PHP)**: Menguji interaksi controller, middleware, model Eloquent, validasi Form Request, dan otorisasi Policy secara otomatis di tingkat server (Total: **101 tests, 373 assertions**).
2. **API End-to-End Testing (Postman Collection Runner)**: Menguji endpoint RESTful API v1 secara langsung menggunakan kontrak JSON, mencakup seluruh skenario *Happy Path* dan *Negative Path* (Total: **43 request test cases**).

---

## 🧪 2. Panduan Pengujian Otomatis (Pest Framework)

### 2.1 Prasyarat Menjalankan Pengujian
Pastikan database pengujian lokal atau container database aktif (PostgreSQL 17) dan dependensi telah terinstal:
```bash
# Pastikan dependensi vendor terpasang
composer install

# Siapkan database dan migrate schema
php artisan migrate:fresh --seed
```

### 2.2 Perintah Eksekusi Pengujian

| Lingkup Pengujian | Perintah Shell |
| :--- | :--- |
| **Menjalankan Seluruh Test Suite** | `php artisan test --compact` |
| **Menjalankan Kasus Uji UTS (TC-01 s.d TC-12)** | `php artisan test --filter=TournamentSystemTest` |
| **Menjalankan Pengujian Endpoint RESTful API v1** | `php artisan test --filter=TournamentApiTest` |
| **Menjalankan Pengujian Autentikasi Sesi** | `php artisan test --filter=SessionAuthTest` |
| **Memeriksa & Merapikan Format Kode** | `vendor/bin/pint --format agent` |

### 2.3 Daftar 12 Kasus Uji Inti (TC-01 s.d TC-12)

| ID | Kasus Uji | Skenario Pengujian | Hasil Pengujian | Status |
| :---: | :--- | :--- | :--- | :---: |
| **TC-01** | Login & Logout | Login valid masuk sesi; password salah ditolak; logout membersihkan sesi. | Kredensial valid redirect sukses; kredensial invalid menampilkan error session. | ✅ LULUS |
| **TC-02** | Katalog & Empty State | Katalog menampilkan daftar turnamen; filter tanpa hasil menampilkan UI *empty state*. | Komponen `tournaments/Index.vue` merender state kosong dengan tombol reset. | ✅ LULUS |
| **TC-03** | Tambah Valid (Pendaftaran) | Pendaftaran tim kapten dengan roster lengkap dan nomor WhatsApp valid. | Record tersimpan di DB `tournament_registrations`, status default `pending`. | ✅ LULUS |
| **TC-04** | Ubah Valid (Pre-fill Data) | Form edit memuat data lama dan menyimpan perubahan tanpa merusak relasi. | Nilai lama terisi di input form; perubahan nama tim terupdate permanen. | ✅ LULUS |
| **TC-05** | Input Kosong / Spasi (*Negative*) | Pengiriman payload kosong `{}` atau hanya spasi pada kolom nama tim dan anggota. | Server menolak dengan respons HTTP 422 disertai pesan error spesifik per-kolom. | ✅ LULUS |
| **TC-06** | Batas Kuota Turnamen (*Negative*) | Pendaftaran tim pada turnamen yang kuota slot maksimalnya (`max_teams`) sudah penuh. | Server menolak pendaftaran baru dengan error HTTP 422: *"Kuota slot turnamen sudah penuh"*. | ✅ LULUS |
| **TC-07** | Referensi FK Tidak Sah (*Negative*) | Pendaftaran dengan `tournament_id` fiktif (`999999`) yang tidak ada di database. | Server menolak via validasi foreign key HTTP 422 tanpa membuat record orphan. | ✅ LULUS |
| **TC-08** | Akses Tanpa Login (*Negative*) | Akses langsung ke endpoint pembuatan turnamen atau pendaftaran tim tanpa autentikasi. | Ditolak server dan diarahkan ke halaman login (`HTTP 302 Redirect` ke `/login`). | ✅ LULUS |
| **TC-09** | Akses Tidak Berhak (*Negative*) | Peserta biasa mencoba mengubah turnamen admin atau menyetujui (*approve*) tim sendiri. | Policy menolak dengan respons `HTTP 403 Forbidden`; data DB tidak berubah. | ✅ LULUS |
| **TC-10** | ID Data Tidak Ditemukan (*Negative*) | Akses detail turnamen atau registrasi dengan ID fiktif atau bukan angka. | Ditangani secara aman dengan respons `HTTP 404 Not Found` tanpa crash SQL. | ✅ LULUS |
| **TC-11** | Duplikasi Nama Tim (*Negative*) | Mendaftarkan nama tim yang sama persis pada turnamen yang sama. | Server mendeteksi duplikasi dan menolak dengan error validasi HTTP 422. | ✅ LULUS |
| **TC-12** | Seed & Instalasi Ulang | Menjalankan migrasi ulang dan seeding data demo dari database kosong. | Seluruh tabel terbentuk; 3 akun demo (Admin & 2 Kapten) dan 5 master game terisi. | ✅ LULUS |

### 2.4 Bukti Hasil Eksekusi Pest
```json
{"tool":"pest","result":"passed","tests":101,"passed":97,"assertions":373,"duration_ms":6168,"skipped":4}
```
> **Catatan**: 4 tes yang *skipped* merupakan tes bawaan starter kit Laravel untuk fitur opsional yang tidak diaktifkan pada lingkungan lokal. Seluruh 97 tes fungsional aplikasi lulus 100%.

---

## 📮 3. Panduan Pengujian API dengan Postman

Berkas koleksi pengujian API tersedia pada repositori:
📁 [`docs/postman_collection_pendaftaran_turnamen.json`](file:///D:/Projects/PHP_Projects/pendaftaran_turnamen_game/docs/postman_collection_pendaftaran_turnamen.json)

### 3.1 Cara Mengimpor & Menyiapkan Koleksi
1. Buka aplikasi **Postman**.
2. Klik tombol **Import** pada pojok kiri atas -> Pilih berkas `docs/postman_collection_pendaftaran_turnamen.json`.
3. Pastikan server Laravel aktif di terminal:
   ```bash
   php artisan serve
   ```
4. Variabel bawaan koleksi (*Collection Variables*) otomatis terkonfigurasi:
   - `base_url`: `http://127.0.0.1:8000/api/v1`
   - `password_demo`: `password`
   - `token_admin`, `token_kapten1`, `token_kapten2`: *(Terisi otomatis saat login)*
   - `tournament_id`, `game_id`: *(Terisi otomatis dari katalog)*
   - `registration_kapten1`, `registration_kapten2`: *(Terisi otomatis saat pendaftaran)*

### 3.2 Menjalankan Automated Runner di Postman
1. Klik kanan pada nama koleksi: **Sistem Pendaftaran Turnamen Game API v1 - UTS Mini Project**.
2. Pilih menu **Run collection**.
3. Pastikan urutan request tidak diubah (urutan telah disusun berantai dengan penyimpanan token dan ID secara otomatis).
4. Klik tombol **Run Sistem Pendaftaran Turnamen Game...**.

---

### 3.3 Rincian 43 Skenario Request Postman

#### Bagian 1: Autentikasi & Proteksi Akses (TC-01 & TC-08)
- **01. Login Kapten1 (Andi)**: `POST {{base_url}}/auth/login` → **Status 200 OK**. Menyimpan `token_kapten1`, `user_kapten1`, dan membuat suffix nama tim dinamis.
- **02. Login Kapten2 (Budi)**: `POST {{base_url}}/auth/login` → **Status 200 OK**. Menyimpan `token_kapten2` dan `user_kapten2`.
- **03. Login Admin Panitia**: `POST {{base_url}}/auth/login` → **Status 200 OK**. Menyimpan `token_admin` dan `user_admin`.
- **04. Login Password Salah (Negative)**: `POST {{base_url}}/auth/login` → **Status 401 Unauthorized**. Pesan error: *"Kredensial tidak valid"*.
- **05. Akses Endpoint Tanpa Token (Negative)**: `GET {{base_url}}/me` → **Status 401 Unauthorized**.

#### Bagian 2: Katalog Data Master & Publik (TC-02 & TC-10)
- **06. Daftar Master Game**: `GET {{base_url}}/games` → **Status 200 OK**. Mengambil ID game MOBA / FPS.
- **07. Daftar Katalog Turnamen**: `GET {{base_url}}/tournaments?per_page=5&page=1` → **Status 200 OK**. Paginasi aktif, menyimpan ID turnamen berstatus *Open*.
- **08. Detail Turnamen Terpilih**: `GET {{base_url}}/tournaments/{{tournament_id}}` → **Status 200 OK**. Menampilkan data regulasi, slot, dan game.
- **09. Detail Turnamen ID Bukan Angka (Negative)**: `GET {{base_url}}/tournaments/turnamen-fiktif-abc` → **Status 404 Not Found**. Proteksi model binding PostgreSQL bigint.
- **10. Detail Turnamen ID Tidak Ada (Negative)**: `GET {{base_url}}/tournaments/999999` → **Status 404 Not Found**.
- **11. Pagination Melebihi Batas Maksimal (Negative)**: `GET {{base_url}}/tournaments?per_page=51` → **Status 422 Unprocessable Content**. Validasi batas `per_page` maksimal 50.

#### Bagian 3: Alur Transaksi Pendaftaran Tim (TC-03 & TC-04)
- **12. Pendaftaran Tim Kapten1 (Happy Path)**: `POST {{base_url}}/registrations` (Token Kapten1) → **Status 201 Created**. Status awal `pending`, header `Location` terbit.
- **13. Pendaftaran Tim Kapten2 (Happy Path)**: `POST {{base_url}}/registrations` (Token Kapten2) → **Status 201 Created**.
- **14. Daftar Pendaftaran Sendiri (Isolasi Kapten1)**: `GET {{base_url}}/registrations` (Token Kapten1) → **Status 200 OK**. Memverifikasi hanya data tim milik Kapten1 yang tampil.
- **15. Detail Pendaftaran Aman**: `GET {{base_url}}/registrations/{{registration_kapten1}}` → **Status 200 OK**. Verifikasi field sensitif (`password`, `token`) tidak bocor.

#### Bagian 4: Pengujian Otorisasi & Kepemilikan Data (TC-09 Negative)
- **16. Kapten2 Mengintip Data Kapten1**: `GET {{base_url}}/registrations/{{registration_kapten1}}` (Token Kapten2) → **Status 403 Forbidden**.
- **17. Kapten2 Mengubah Data Kapten1**: `PUT {{base_url}}/registrations/{{registration_kapten1}}` (Token Kapten2) → **Status 403 Forbidden**.
- **18. Kapten2 Menghapus Data Kapten1**: `DELETE {{base_url}}/registrations/{{registration_kapten1}}` (Token Kapten2) → **Status 403 Forbidden**.
- **19. Peserta Mengubah Turnamen Admin**: `PUT {{base_url}}/tournaments/{{tournament_id}}` (Token Kapten1) → **Status 403 Forbidden**.
- **20. Peserta Menghapus Turnamen Admin**: `DELETE {{base_url}}/tournaments/{{tournament_id}}` (Token Kapten1) → **Status 403 Forbidden**.
- **21. Peserta Menyetujui Status Tim Sendiri**: `PATCH {{base_url}}/registrations/{{registration_kapten1}}/status` (Token Kapten1) → **Status 403 Forbidden**.

#### Bagian 5: Validasi Input & Aturan Bisnis Server (TC-05, TC-07, TC-11 Negative)
- **22. Pendaftaran Nama Tim Kosong**: `POST {{base_url}}/registrations` → **Status 422 Unprocessable Content**.
- **23. Pendaftaran Roster Kosong**: `POST {{base_url}}/registrations` → **Status 422 Unprocessable Content**.
- **24. Pendaftaran Format WhatsApp Salah**: `POST {{base_url}}/registrations` → **Status 422 Unprocessable Content**.
- **25. Pemalsuan Pemilik Pendaftaran (user_id spoofing)**: `POST {{base_url}}/registrations` → **Status 422 Unprocessable Content**.
- **26. Pendaftaran ID Turnamen Tidak Ada**: `POST {{base_url}}/registrations` → **Status 422 Unprocessable Content**.
- **27. Duplikasi Nama Tim pada Turnamen Sama**: `POST {{base_url}}/registrations` → **Status 422 Unprocessable Content**.

#### Bagian 6: Siklus Hidup Status Pendaftaran (TC-04 & State Locking)
- **28. Update Data Tim Sendiri Kapten1 (Happy Path)**: `PUT {{base_url}}/registrations/{{registration_kapten1}}` → **Status 200 OK**. Nama tim dan nomor WA terupdate.
- **29. Admin Menyetujui (Approve) Pendaftaran Kapten1**: `PATCH {{base_url}}/registrations/{{registration_kapten1}}/status` (Token Admin) → **Status 200 OK**. Status berubah menjadi `approved`.
- **30. Status Approved Tidak Bisa Diubah Peserta**: `PUT {{base_url}}/registrations/{{registration_kapten1}}` (Token Kapten1) → **Status 403 Forbidden**. Penguncian data roster yang telah disetujui.
- **31. Status Approved Tidak Bisa Dihapus Peserta**: `DELETE {{base_url}}/registrations/{{registration_kapten1}}` (Token Kapten1) → **Status 403 Forbidden**.

#### Bagian 7: Dashboard Metrik & Laporan (TC-09 Negative & Admin Happy Path)
- **32. Akses Laporan Summary oleh Peserta**: `GET {{base_url}}/reports/summary` (Token Kapten1) → **Status 403 Forbidden**.
- **33. Akses Laporan Summary oleh Admin**: `GET {{base_url}}/reports/summary` (Token Admin) → **Status 200 OK**. Metrik `tournament_count`, `registration_count`, dan `game_count` tampil valid.

#### Bagian 8: Pembatalan, Pembersihan Data, & Teardown Sesi (TC-01, TC-10)
- **34. Pembatalan Pendaftaran Pending Kapten2**: `DELETE {{base_url}}/registrations/{{registration_kapten2}}` (Token Kapten2) → **Status 204 No Content**.
- **35. Pendaftaran Terhapus Dikonfirmasi 404**: `GET {{base_url}}/registrations/{{registration_kapten2}}` → **Status 404 Not Found**.
- **36. Pembatalan Pendaftaran Approved Kapten1 oleh Admin (Cleanup)**: `DELETE {{base_url}}/registrations/{{registration_kapten1}}` (Token Admin) → **Status 204 No Content**.
- **37. Pendaftaran Kapten1 Terhapus Dikonfirmasi 404**: `GET {{base_url}}/registrations/{{registration_kapten1}}` → **Status 404 Not Found**.
- **38. Logout Kapten1**: `POST {{base_url}}/auth/logout` → **Status 204 No Content**.
- **39. Token Kapten1 Dicabut**: `GET {{base_url}}/me` → **Status 401 Unauthorized**.
- **40. Logout Kapten2**: `POST {{base_url}}/auth/logout` → **Status 204 No Content**.
- **41. Token Kapten2 Dicabut**: `GET {{base_url}}/me` → **Status 401 Unauthorized**.
- **42. Logout Admin**: `POST {{base_url}}/auth/logout` → **Status 204 No Content**.
- **43. Token Admin Dicabut**: `GET {{base_url}}/me` → **Status 401 Unauthorized**.

---

## 🔒 4. Fitur Keandalan Khusus pada Pengujian

1. **Mekanisme Idempoten & Anti-Konflik (*Zero-Collision*)**:
   Pada request `01. Login Kapten1`, script otomatis meng-generate nama tim dinamis menggunakan timestamp unix (`Garuda Nusantara {suffix}`). Hal ini menjamin bahwa seluruh pengujian dapat di-*run* berulang kali di Postman tanpa pernah mengalami galat keunikan nama tim (*duplicate key*).
2. **Penanganan PostgreSQL Bigint Type Safety**:
   Model Eloquent [`Tournament`](file:///D:/Projects/PHP_Projects/pendaftaran_turnamen_game/app/Models/Tournament.php) dan [`TournamentRegistration`](file:///D:/Projects/PHP_Projects/pendaftaran_turnamen_game/app/Models/TournamentRegistration.php) menerapkan proteksi `resolveRouteBindingQuery()` untuk memastikan parameter ID non-angka (seperti string URL) tidak dieksekusi mentah ke kolom `bigint`, sehingga server mengembalikan `404 Not Found` secara elegan tanpa menimbulkan exception SQL `500 Server Error`.
3. **Pembersihan Otomatis (*Auto-Teardown*)**:
   Setelah seluruh skenario pengujian pendaftaran selesai, runner otomatis menghapus record uji coba dan mencabut seluruh token sesi autentikasi, meninggalkan database dalam kondisi bersih dan konsisten.

---

## 📊 5. Kesimpulan Hasil Akhir

| Parameter Evaluasi | Target Kebutuhan UTS | Hasil Aktual Sistem | Keterangan |
| :--- | :---: | :---: | :---: |
| **Kasus Uji Minimal** | 12 Kasus Uji | 101 Kasus Uji (Pest) & 43 Request (Postman) | Melebihi standar minimum |
| **Persentase Kelulusan** | 100% | **100% Passed (0 Failed)** | Sempurna |
| **Negative Path Enforcement** | Teruji di server | Teruji (401, 403, 404, 422) | Seluruh proteksi aktif |
| **Integritas Relasi Basis Data** | Berelasi utuh | `games` → `tournaments` → `registrations` | Utuh & konsisten |
| **Kesiapan Demo & Presentasi** | Siap didemokan | Siap via Web UI & Postman Runner | Siap uji langsung |
