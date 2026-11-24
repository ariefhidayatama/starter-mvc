# 01 — Instalasi Project & Struktur Folder

> Tahap: 2 · Dibuat: 2026-11-10 · Status: selesai

## 1. Tujuan

Menyiapkan project Laravel 13 dengan tampilan web **Inertia + React + TypeScript** dan menyiapkan fondasi **REST API**, semuanya dalam satu project. Di akhir dokumen ini, Anda punya:

- Aplikasi web dengan login, register, verifikasi email, 2FA, dan halaman pengaturan profil (dari starter kit).
- Database MySQL 8.4 dengan engine InnoDB.
- Sanctum terpasang (dipakai untuk token API di Tahap 3).
- Pest untuk testing, dengan database test MySQL terpisah.
- Kerangka folder sesuai rancangan arsitektur.

## 2. Konsep yang Perlu Dipahami

| Istilah              | Penjelasan singkat                                                                                                                                                                             |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Starter Kit**      | Template project resmi Laravel yang sudah berisi halaman auth & layout. Kita tidak membuat halaman login dari nol.                                                                             |
| **Inertia.js**       | "Lem" antara Laravel dan React. Controller Laravel mengirim nama halaman + data (_props_), lalu Inertia menampilkan komponen React yang sesuai **tanpa** perlu membuat API terpisah untuk web. |
| **Fortify**          | Paket backend auth tanpa tampilan: menangani login, register, reset password, 2FA. Tampilannya disediakan starter kit (React).                                                                 |
| **Wayfinder**        | Membuat fungsi TypeScript dari route Laravel. Di React kita menulis `dashboard()` alih-alih string `'/dashboard'`, sehingga typo URL tertangkap saat build.                                    |
| **Sanctum**          | Paket auth ringan: session untuk web, token untuk mobile/API.                                                                                                                                  |
| **InnoDB vs MyISAM** | Dua "mesin penyimpanan" MySQL. **InnoDB** mendukung transaksi, row lock, dan foreign key. **MyISAM** tidak. Project ini **wajib InnoDB**.                                                      |
| **Pest**             | Framework testing dengan sintaks ringkas (`it('...', fn)`), berjalan di atas PHPUnit.                                                                                                          |

## 3. Lingkungan yang Dipakai

| Komponen | Versi           | Catatan                                                      |
| -------- | --------------- | ------------------------------------------------------------ |
| PHP      | 8.5 (WAMP)      | Laravel 13 butuh minimal 8.3                                 |
| Composer | 2.11            |                                                              |
| Node.js  | 22              |                                                              |
| MySQL    | 8.4.7 (WAMP)    | **Port 3308**. Port 3306 & 3307 di mesin ini dipakai MariaDB |
| Laravel  | 13.40           |                                                              |
| Inertia  | 3.x             |                                                              |
| React    | 19 + TypeScript |                                                              |

## 4. Langkah Pembuatan

### Langkah 1 — Membuat project dari starter kit

```bash
composer create-project laravel/react-starter-kit:dev-main nama-folder
```

> **Kenapa `:dev-main`?** Tanpa itu, Composer mengambil rilis lama di Packagist (Laravel 12, belum ada Pest/Fortify). Installer resmi `laravel new` juga mengambil dari branch `main`.

> **Jika folder tujuan sudah berisi file** (seperti project ini yang sudah punya `docs/`), buat dulu di subfolder sementara, lalu pindahkan isinya ke root.

> **Error "Could not delete ... antivirus or Windows Search Indexer"** muncul jika antivirus mengunci file saat Composer mengekstrak paket. Solusinya: jalankan `composer install` sekali lagi.

### Langkah 2 — Memilih fitur auth starter kit

Starter kit menyediakan perintah untuk memilih fitur auth yang ingin dipertahankan:

```bash
php artisan install:features --answers='{"auth_features":["registration","email-verification","2fa","password-confirmation"]}'
```

| Fitur                 | Dipakai?  | Alasan                                                                                            |
| --------------------- | --------- | ------------------------------------------------------------------------------------------------- |
| Registration          | Ya        | Customer mendaftar sendiri                                                                        |
| Email verification    | Ya        | Memastikan email valid. Di lokal, email ditulis ke `storage/logs/laravel.log` (`MAIL_MAILER=log`) |
| Two-factor auth (2FA) | Ya        | Keamanan akun admin/staff                                                                         |
| Password confirmation | Ya        | Minta password ulang sebelum aksi sensitif                                                        |
| Passkeys              | **Tidak** | Butuh WebAuthn/HTTPS, menambah kerumitan yang belum diperlukan                                    |

Perintah ini juga menjalankan `npm install`, membuat file Wayfinder, dan mem-build aset. Setelah selesai, perintah ini menghapus dirinya sendiri.

### Langkah 3 — Membuat database

Di MySQL 8.4 (WAMP), lewat phpMyAdmin atau command line:

```sql
CREATE DATABASE starter_mvc      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE starter_mvc_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

- `utf8mb4` mendukung semua karakter, termasuk emoji.
- `starter_mvc_test` khusus untuk automated test. Isinya dihapus dan dibuat ulang setiap kali test berjalan, jadi **jangan** arahkan test ke database development.

### Langkah 4 — Mengatur `.env`

```dotenv
APP_NAME="Starter MVC"
APP_LOCALE=en                 # teks UI berbahasa Inggris
APP_FAKER_LOCALE=id_ID        # data palsu (seeder) bernuansa Indonesia

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3308                  # sesuaikan dengan port MySQL Anda
DB_DATABASE=starter_mvc
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database     # → redis di Tahap 7
CACHE_STORE=database          # → redis di Tahap 6
REDIS_CLIENT=predis           # Windows tanpa ekstensi phpredis
```

Lalu buat application key:

```bash
php artisan key:generate
```

> `.env` berisi rahasia (APP_KEY, password DB) dan **tidak boleh** di-commit ke Git. `.env.example` adalah salinan tanpa rahasia, sebagai contoh untuk anggota tim lain.

### Langkah 5 — Memaksa engine InnoDB

Saat pertama kali menjalankan migrasi, muncul error:

```
SQLSTATE[42000]: Specified key was too long; max key length is 1000 bytes
```

**Penyebab:** MySQL di WAMP memakai **MyISAM** sebagai engine default. Batas panjang index MyISAM 1000 byte, sedangkan index `email` utf8mb4 butuh lebih dari itu.

Cara mengecek:

```sql
SELECT @@default_storage_engine;   -- hasil: MyISAM
```

**Solusi** di `config/database.php`, koneksi `mysql`:

```php
// Dipaksa InnoDB: WAMP memakai MyISAM sebagai default, padahal
// transaksi, row lock (lockForUpdate) & foreign key butuh InnoDB.
'engine' => env('DB_ENGINE', 'InnoDB'),
```

Kenapa diperbaiki di Laravel, bukan di `my.ini` MySQL? Supaya project tetap benar di mesin **siapa pun**, tanpa bergantung pada konfigurasi server masing-masing developer.

```bash
php artisan migrate:fresh
```

### Langkah 6 — Memasang API (Sanctum + routes/api.php)

```bash
php artisan install:api
```

Perintah ini:

1. Memasang paket `laravel/sanctum`.
2. Membuat migration tabel `personal_access_tokens` (tempat menyimpan token mobile).
3. Membuat `routes/api.php` dan mendaftarkannya di `bootstrap/app.php`.

Detail routing dibahas di [02 — Routing & Versioning](02-routing-dan-versioning.md).

### Langkah 7 — Strict mode Eloquent

Di `app/Providers/AppServiceProvider.php`:

```php
// Strict mode Eloquent di luar production. Bug berikut akan langsung
// melempar exception (bukan diam-diam lambat/salah):
// - Lazy loading (penyebab N+1 query)  → wajib pakai with()/load()
// - Mengisi atribut yang tidak ada di $fillable
// - Membaca atribut yang tidak ikut di-SELECT
Model::shouldBeStrict(! app()->isProduction());
```

**Contoh bug yang tertangkap:**

```php
$products = Product::all();          // 1 query
foreach ($products as $p) {
    echo $p->category->name;         // +1 query PER produk → N+1
}
// Dengan strict mode: LazyLoadingViolationException → Anda dipaksa menulis:
$products = Product::with('category')->get();   // total 2 query saja
```

Di production, strict mode **dimatikan** supaya bug kecil yang lolos tidak membuat aplikasi error di depan user. Bug tersebut seharusnya sudah tertangkap di local dan oleh test.

### Langkah 8 — Pest & database test

```bash
composer require pestphp/pest pestphp/pest-plugin-laravel --dev -W
```

`tests/Pest.php`:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

`phpunit.xml`, diubah dari SQLite ke MySQL:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="starter_mvc_test"/>
```

> **Kenapa tidak SQLite in-memory (default Laravel)?** Lebih cepat, tetapi SQLite tidak mengenal **stored procedure** dan **FULLTEXT index** yang kita pakai di Tahap 5. Test harus berjalan di engine yang sama dengan production.

Menjalankan test:

```bash
php artisan test        # atau: vendor/bin/pest
```

### Langkah 9 — Kerangka folder

Folder berikut dibuat sekarang (berisi `.gitkeep` agar tetap tercatat di Git walau kosong). Isinya ditambahkan di tahap-tahap berikutnya.

```
app/
├── Actions/            ← use-case tulis (CreateProductAction, PlaceOrderAction)
├── Enums/              ← RoleName, PermissionName, OrderStatus
├── Exceptions/         ← BusinessException, ApiExceptionRenderer
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/     ← controller API (JSON)
│   │   ├── Settings/   ← controller web bawaan starter kit
│   │   └── <Fitur>/    ← controller web fitur kita (Catalog/, Admin/, ...)
│   ├── Middleware/
│   ├── Requests/
│   │   ├── Api/V1/     ← request khusus API (mis. LoginRequest + device_name)
│   │   ├── Settings/   ← bawaan starter kit
│   │   └── <Fitur>/    ← request yang DIPAKAI BERSAMA web & API
│   └── Resources/V1/   ← bentuk JSON API
├── Jobs/
├── Observers/
├── Policies/
├── Services/
└── Support/            ← ApiResponse, CacheKey
database/sql/procedures/  ← file .sql stored procedure
k6/{scenarios,results}/   ← load test
```

**Penyesuaian dari rancangan Tahap 1:** rancangan awal menaruh controller web di `Controllers/Web/`. Namun starter kit sudah memakai `Controllers/Settings/`, dan **Wayfinder membuat file TypeScript berdasarkan path controller** (`resources/js/actions/App/Http/Controllers/Settings/...`). Memindahkannya akan merusak import di React. Karena itu konvensinya menjadi:

> **Controller API → `Controllers/Api/V1/`. Semua yang lain adalah controller web, dikelompokkan per fitur.**

## 5. Struktur Project Setelah Instalasi

```
starter-mvc/
├── app/                    ← kode PHP aplikasi
├── bootstrap/app.php       ← konfigurasi routing, middleware, exception (pengganti Kernel.php)
├── config/                 ← konfigurasi (membaca nilai dari .env)
├── database/
│   ├── factories/          ← pembuat data palsu untuk test/seeder
│   ├── migrations/         ← versi skema database
│   └── seeders/            ← pengisi data awal
├── public/                 ← satu-satunya folder yang boleh diakses web server
│   └── build/              ← hasil build React (npm run build)
├── resources/
│   ├── css/app.css         ← Tailwind
│   └── js/
│       ├── actions/        ← (otomatis) Wayfinder: fungsi per controller
│       ├── routes/         ← (otomatis) Wayfinder: fungsi per nama route
│       ├── components/ui/  ← komponen shadcn/ui
│       ├── layouts/        ← layout halaman (sidebar, auth)
│       ├── pages/          ← SATU file = SATU halaman Inertia
│       └── app.tsx         ← titik masuk React
├── routes/
│   ├── web.php             ← route web (session)
│   ├── settings.php        ← route halaman pengaturan (starter kit)
│   ├── api.php             ← pintu masuk versi API
│   ├── api/v1.php          ← route API v1
│   └── console.php         ← perintah artisan & jadwal
├── tests/
├── vendor/                 ← paket Composer (jangan diedit)
└── node_modules/           ← paket npm (jangan diedit)
```

> Folder `resources/js/actions` dan `resources/js/routes` **dibuat otomatis** oleh Wayfinder. Jangan diedit manual; file-file itu dibuat ulang saat `npm run dev`/`build`.

## 6. Cara Mencoba

### Satu perintah untuk semua proses development

```bash
composer run dev        # sama dengan: php artisan dev
```

Perintah ini menjalankan **3 proses sekaligus** dalam satu terminal:

| Label    | Proses                     | Fungsi                                                        |
| -------- | -------------------------- | ------------------------------------------------------------- |
| `server` | `php artisan serve`        | Server PHP di `http://localhost:8000`                         |
| `queue`  | `php artisan queue:listen` | Memproses background job (email, export)                      |
| `vite`   | `npm run dev`              | Meng-compile React/TypeScript + hot reload saat file disimpan |

Lihat daftar prosesnya dengan `php artisan dev:list`. Tekan `Ctrl+C` sekali untuk menghentikan semuanya.

Opsi tampilan:

```bash
php artisan dev            # tampilan TUI (panel per proses)
php artisan dev --inline   # output biasa berurutan, diberi label [server]/[queue]/[vite]
php artisan dev --tabs     # satu tab per proses
```

**Mengganti port** (jika 8000 bentrok dengan aplikasi lain), tambahkan di `.env`:

```dotenv
SERVER_PORT=8010
APP_URL=http://localhost:8010
```

Buka `http://localhost:8000` → halaman welcome → Register → cek link verifikasi email di `storage/logs/laravel.log`.

> **Kapan tetap perlu terpisah?** Hampir tidak pernah. Pengecualiannya: saat ingin me-restart satu proses saja. Misalnya setelah mengubah kode Job, queue worker perlu di-restart, sedangkan server & Vite tidak.

Cek API:

```bash
curl http://localhost:8000/api/v1/ping
```

```json
{
    "success": true,
    "message": "pong",
    "data": { "version": "v1", "server_time": "2026-11-10T04:25:20+00:00" }
}
```

Pemeriksaan kualitas kode:

```bash
php artisan test          # automated test (45 test lulus saat dokumen ini ditulis)
composer lint             # merapikan format PHP (Pint)
composer types:check      # analisis statis PHP (PHPStan/Larastan level 7)
npm run types:check       # cek tipe TypeScript
```

## 7. Kesalahan Umum & Cara Mengatasinya

| Gejala                                                     | Penyebab                                                                                | Solusi                                                                                                                                                   |
| ---------------------------------------------------------- | --------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Specified key was too long; max key length is 1000 bytes` | Tabel dibuat dengan MyISAM                                                              | `'engine' => 'InnoDB'` di `config/database.php`, lalu `migrate:fresh`                                                                                    |
| `SQLSTATE[HY000] [2002] connection refused`                | Port MySQL salah / WAMP belum hidup                                                     | Cek ikon WAMP hijau, cek port di WAMP → MySQL → my.ini                                                                                                   |
| Versi MySQL yang terhubung ternyata MariaDB                | Beberapa server DB berjalan di port berbeda                                             | `SELECT VERSION();` lalu sesuaikan `DB_PORT`                                                                                                             |
| `Could not delete ... antivirus` saat composer             | Antivirus/Indexer mengunci file                                                         | Ulangi `composer install`                                                                                                                                |
| PHPStan: `reached configured PHP memory limit: 128M`       | Batas memori PHP default                                                                | `phpstan analyse --memory-limit=1G` (sudah diatur di `composer types:check`)                                                                             |
| Halaman putih / `Vite manifest not found`                  | Aset React belum di-build                                                               | `npm run dev` (development) atau `npm run build`                                                                                                         |
| `composer run dev` → `Error: spawn cmd.exe ENOENT`         | Folder `C:\Windows\System32` tidak ada di PATH, sehingga Node tidak menemukan `cmd.exe` | Tambahkan `C:\Windows\System32` ke PATH (System Properties → Environment Variables → Path), lalu buka ulang terminal/VS Code. Cek dengan `where.exe cmd` |
| `composer run dev` → `Address already in use`              | Port 8000 dipakai aplikasi lain                                                         | Set `SERVER_PORT=8010` di `.env`                                                                                                                         |
| `PDO::MYSQL_ATTR_SSL_CA is deprecated`                     | Config lama di PHP 8.5                                                                  | Config starter kit terbaru sudah memakai `Pdo\Mysql::ATTR_SSL_CA`                                                                                        |

## 8. Latihan

1. Jalankan `SELECT table_name, engine FROM information_schema.tables WHERE table_schema='starter_mvc';` dan pastikan semua tabel InnoDB.
2. Hapus sementara baris `Model::shouldBeStrict(...)`, lalu pikirkan bug apa saja yang tidak akan ketahuan. Kembalikan setelahnya.
3. Buka `resources/js/routes/index.ts` dan cari fungsi `dashboard`. Bandingkan dengan `routes/web.php`.

## 9. Referensi

- Starter kits: https://laravel.com/docs/starter-kits
- Konfigurasi database: https://laravel.com/docs/database
- Eloquent strict mode: https://laravel.com/docs/eloquent#configuring-eloquent-strictness
- Pest: https://pestphp.com/docs
