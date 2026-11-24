# 09 — Deploy ke Production

> Tahap: 9 · Dibuat: 2026-11-24 · Status: selesai

## 1. Tujuan

Memindahkan aplikasi dari lingkungan development (WAMP + `npm run dev`) ke server production
yang aman, cepat, dan mudah di-update. Panduan ini mengasumsikan server **Linux (Ubuntu 24.04)**
dengan **Nginx + PHP-FPM**, karena itu setup production paling umum untuk Laravel. Catatan untuk
server Windows/IIS ada di bagian akhir.

## 2. Konsep yang Perlu Dipahami

| Istilah | Penjelasan |
| --- | --- |
| **Dev vs build** | Saat `npm run dev`, browser memuat ratusan file `.tsx` mentah dari Vite (`:5173`). Di production, `npm run build` menggabungkan dan mengecilkan semuanya menjadi beberapa file di `public/build/` dengan nama ber-hash, sehingga bisa di-cache lama oleh browser. |
| **`public/hot`** | File penanda yang dibuat `npm run dev`. Selama file ini ada, Laravel mengarahkan aset ke Vite dev server. **Tidak boleh ada di production.** |
| **Cache config/route/view** | `php artisan optimize` menyimpan config, route, view, dan event ke file PHP siap pakai, sehingga tiap request tidak perlu membaca ulang `.env` dan file config. |
| **Queue worker** | Proses latar belakang yang menjalankan job antrean (`QUEUE_CONNECTION=database`). Harus selalu hidup dan diawasi Supervisor. |
| **Scheduler** | Satu cron per menit yang menjalankan `schedule:run`. Di proyek ini dipakai untuk `sanctum:prune-expired` (hapus token API kedaluwarsa) setiap hari. |
| **Document root** | Web server **hanya** boleh mengarah ke folder `public/`, bukan ke root proyek, agar `.env` dan kode tidak bisa diakses dari browser. |

## 3. Kebutuhan Server

| Komponen | Versi / catatan |
| --- | --- |
| PHP | **8.3** (sesuai `config.platform.php` di `composer.json`) + FPM |
| Ekstensi PHP | `bcmath ctype curl dom fileinfo gd intl mbstring openssl pdo_mysql tokenizer xml zip` |
| Ekstensi SQL Server | `sqlsrv` + `pdo_sqlsrv` + **Microsoft ODBC Driver 18**, untuk koneksi ERP `wmsreports` |
| Database | MySQL 8 / MariaDB 10.6+ |
| Node.js | 22 LTS, hanya untuk build aset (bisa juga build di mesin lain) |
| Composer | 2.x |
| Lainnya | Nginx, Supervisor, Certbot (HTTPS), Git |

## 4. Langkah Deploy Pertama Kali

### Langkah 1 — Siapkan server

```bash
sudo apt update
sudo apt install -y nginx supervisor git unzip mysql-server \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-dev

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

**Driver SQL Server (wajib untuk integrasi ERP, lihat [08e](08e-integrasi-erp-sql-server.md)):**

```bash
# Microsoft ODBC Driver 18
curl -sSL https://packages.microsoft.com/keys/microsoft.asc | sudo gpg --dearmor -o /usr/share/keyrings/microsoft.gpg
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft.gpg] https://packages.microsoft.com/ubuntu/24.04/prod noble main" \
  | sudo tee /etc/apt/sources.list.d/mssql-release.list
sudo apt update
sudo ACCEPT_EULA=Y apt install -y msodbcsql18 unixodbc-dev

# Ekstensi PHP
sudo pecl install sqlsrv pdo_sqlsrv
echo "extension=sqlsrv.so"     | sudo tee /etc/php/8.3/mods-available/sqlsrv.ini
echo "extension=pdo_sqlsrv.so" | sudo tee /etc/php/8.3/mods-available/pdo_sqlsrv.ini
sudo phpenmod sqlsrv pdo_sqlsrv
sudo systemctl restart php8.3-fpm

php -m | grep -i sqlsrv   # harus muncul: pdo_sqlsrv, sqlsrv
```

### Langkah 2 — Buat database

```sql
CREATE DATABASE starter_mvc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'starter_mvc'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON starter_mvc.* TO 'starter_mvc'@'localhost';
FLUSH PRIVILEGES;
```

> Jangan memakai user `root` untuk aplikasi.

### Langkah 3 — Ambil kode

```bash
sudo mkdir -p /var/www/mvc-83
sudo chown $USER:www-data /var/www/mvc-83
git clone <url-repo> /var/www/mvc-83
cd /var/www/mvc-83
```

> Proyek ini belum memakai Git. Sebelum deploy, sebaiknya `git init` dan push ke repo privat agar
> update berikutnya cukup `git pull`. Alternatifnya upload via SFTP/rsync **tanpa** folder
> `node_modules/`, `vendor/`, `public/hot`, `public/build/`, `storage/logs/*`, dan `.env`.

### Langkah 4 — Isi `.env` production

```bash
cp .env.example .env
nano .env
```

Nilai yang **wajib** diubah dibanding development:

```dotenv
APP_NAME="Starter MVC"
APP_ENV=production
APP_DEBUG=false                 # WAJIB false, kalau true error akan menampilkan kode & kredensial
APP_URL=https://app.domainanda.com

LOG_CHANNEL=daily               # file log dipecah per hari
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=starter_mvc
DB_USERNAME=starter_mvc
DB_PASSWORD=GANTI_PASSWORD_KUAT

# ERP SQL Server: arahkan ke database production, bukan *_training
WMSREPORTS_DB_HOST=...
WMSREPORTS_DB_PORT=...
WMSREPORTS_DB_DATABASE=wmsreports
WMSREPORTS_DB_USERNAME=...
WMSREPORTS_DB_PASSWORD=...
WMSREPORTS_DB_TRUST_SERVER_CERTIFICATE=false   # true hanya jika sertifikat server ERP self-signed

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true      # cookie hanya dikirim lewat HTTPS
SESSION_ENCRYPT=true

QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp                # ganti dari "log" agar email reset password benar-benar terkirim
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@domainanda.com
```

Lalu buat kunci aplikasi (**sekali saja**; jangan dijalankan ulang, karena session dan data
terenkripsi akan rusak):

```bash
php artisan key:generate
```

### Langkah 5 — Install dependensi & build aset

Urutannya penting: **Composer dulu, baru npm**, karena plugin Vite `wayfinder` menjalankan
`php artisan wayfinder:generate` saat build sehingga butuh `vendor/` sudah ada.

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build          # hasil di public/build/; font Plus Jakarta Sans diunduh dari Bunny Fonts saat build
rm -f public/hot       # pastikan tidak ada sisa dari dev
```

> `npm run build` butuh akses internet (mengunduh font) dan RAM ±2 GB. Kalau server kecil, build
> di laptop lalu upload folder `public/build/` saja. Server production tidak butuh `node_modules/`.

### Langkah 6 — Migrasi & data awal

```bash
php artisan migrate --force
```

**Jangan** jalankan `php artisan db:seed` (DatabaseSeeder): seeder itu membuat akun demo
berpassword `password` dan 25 customer palsu. Untuk production jalankan seeder yang dibutuhkan saja,
**sekali** di instalasi pertama:

```bash
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=MenuSeeder --force
```

> `MenuSeeder` memakai `create()`, jadi kalau dijalankan dua kali menunya akan dobel. Jalankan
> hanya di database kosong.

Buat akun admin pertama dengan password kuat:

```bash
php artisan tinker
```

```php
$u = \App\Models\User::create([
    'name' => 'Administrator',
    'email' => 'admin@domainanda.com',
    'password' => 'PASSWORD_KUAT_DI_SINI',   // di-hash otomatis oleh cast 'hashed'
]);
$u->forceFill(['email_verified_at' => now()])->save();  // kolom ini tidak ada di $fillable
$u->assignRole('admin');
```

Cek koneksi ERP:

```bash
php artisan wmsreports:check
```

### Langkah 7 — Storage & permission folder

```bash
php artisan storage:link
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

### Langkah 8 — Optimasi

```bash
php artisan optimize        # cache config, route, view, event
php artisan scramble:cache  # cache dokumentasi API (/docs/api)
```

> Setelah `config:cache`, fungsi `env()` **hanya** bekerja di dalam folder `config/`. Kode di
> `app/` harus memakai `config('...')`.

### Langkah 9 — Nginx

`/etc/nginx/sites-available/mvc-83`:

```nginx
server {
    listen 80;
    server_name app.domainanda.com;
    root /var/www/mvc-83/public;          # HARUS ke public/

    index index.php;
    charset utf-8;
    client_max_body_size 20M;             # sesuaikan dengan ukuran upload/import Excel

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Aset hasil build bernama hash → aman di-cache 1 tahun
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/mvc-83 /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# HTTPS gratis dari Let's Encrypt
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d app.domainanda.com
```

### Langkah 10 — Queue worker (Supervisor)

`/etc/supervisor/conf.d/mvc-83-worker.conf`:

```ini
[program:mvc-83-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mvc-83/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/mvc-83/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

### Langkah 11 — Scheduler (cron)

```bash
sudo crontab -u www-data -e
```

Tambahkan:

```cron
* * * * * cd /var/www/mvc-83 && php artisan schedule:run >> /dev/null 2>&1
```

Cek jadwal: `php artisan schedule:list` (harus ada `sanctum:prune-expired`).

## 5. Update Aplikasi (Deploy Berikutnya)

Simpan sebagai `deploy.sh` di root proyek:

```bash
#!/usr/bin/env bash
set -e
cd /var/www/mvc-83

php artisan down --retry=30          # tampilkan halaman maintenance

git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
rm -f public/hot

php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan scramble:cache
php artisan permission:cache-reset   # role/permission baru langsung terbaca

php artisan queue:restart            # worker memuat kode baru
sudo systemctl reload php8.3-fpm     # reset OPcache

php artisan up
```

```bash
chmod +x deploy.sh
./deploy.sh
```

> **Backup dulu sebelum deploy yang berisi migrasi**:
> `mysqldump -u starter_mvc -p starter_mvc > ~/backup-$(date +%F-%H%M).sql`

## 6. Checklist Keamanan

- [ ] `APP_ENV=production` dan `APP_DEBUG=false`
- [ ] `APP_KEY` terisi dan **tidak pernah diganti** setelah live
- [ ] Document root Nginx = `public/`; `https://domain/.env` harus 403/404
- [ ] HTTPS aktif dan `SESSION_SECURE_COOKIE=true`
- [ ] Tidak ada akun demo `*@example.com` / password `password`
- [ ] User database khusus aplikasi, bukan `root`
- [ ] Kredensial ERP memakai akun **read-only** di SQL Server, kalau memungkinkan
- [ ] `/docs/api` (Scramble) hanya bisa dibuka user dengan permission `api.access` (gate `viewApiDocs`)
- [ ] File `.env` permission `640`, pemilik user deploy, group `www-data`
- [ ] Backup database terjadwal (harian) dan pernah dicoba restore
- [ ] Firewall: hanya port 22, 80, 443 yang terbuka (`sudo ufw allow 'Nginx Full' && sudo ufw allow OpenSSH && sudo ufw enable`)

## 7. Verifikasi Setelah Deploy

1. Buka situs → DevTools → Network:
   - Tidak ada request ke `:5173` / `@vite` / `@react-refresh`.
   - JS & CSS dimuat dari `/build/assets/*.js` dengan nama ber-hash.
   - Refresh kedua: aset berstatus *cached* (tidak diunduh ulang).
2. Login sebagai admin → buka Users, Roles, Permissions, Menus.
3. Buka halaman yang memakai data ERP → data `wmsreports` muncul.
4. Coba endpoint API dengan token (`Authorization: Bearer ...`).
5. `tail -f storage/logs/laravel-$(date +%F).log` → tidak ada error baru.
6. `sudo supervisorctl status` → worker `RUNNING`.

## 8. Troubleshooting

| Gejala | Penyebab & solusi |
| --- | --- |
| Halaman putih, console: request ke `127.0.0.1:5173` gagal | File `public/hot` masih ada. Hapus, lalu `php artisan optimize:clear`. |
| `Vite manifest not found` | `npm run build` belum dijalankan / `public/build/` tidak ter-upload. |
| Error 500 tanpa detail | Lihat `storage/logs/`. Pastikan `storage/` & `bootstrap/cache/` bisa ditulis `www-data`. |
| Perubahan `.env` tidak berpengaruh | Config ter-cache. Jalankan `php artisan optimize:clear && php artisan optimize`. |
| `could not find driver (sqlsrv)` | Ekstensi `pdo_sqlsrv` belum aktif untuk **FPM** (bukan cuma CLI). Cek `/etc/php/8.3/fpm/conf.d/`. |
| `SSL Provider: certificate verify failed` ke ERP | Server ERP pakai sertifikat self-signed. Set `WMSREPORTS_DB_TRUST_SERVER_CERTIFICATE=true`. |
| `419 Page Expired` setelah login | `APP_URL` / domain tidak cocok, atau `SESSION_SECURE_COOKIE=true` tapi situs dibuka lewat HTTP. |
| Menu/permission baru tidak muncul | Cache permission. Jalankan `php artisan permission:cache-reset`. |
| Job antrean tidak jalan | Worker mati, cek `supervisorctl status`. Setelah deploy wajib `queue:restart`. |
| `npm run build` gagal "wayfinder" | `composer install` belum dijalankan, atau `php` tidak ada di PATH. |

## 9. Catatan: Deploy di Windows Server (IIS / WAMP)

Kalau server production memakai Windows:

- Langkah 4–8 sama persis (`.env`, `composer install --no-dev`, `npm run build`, `migrate --force`,
  `optimize`).
- Arahkan document root IIS/Apache ke folder `public\`. Untuk IIS, gunakan `web.config` dengan
  URL Rewrite ke `index.php`.
- Driver SQL Server: unduh **Microsoft Drivers for PHP for SQL Server** (`php_sqlsrv_83_ts_x64.dll`,
  `php_pdo_sqlsrv_83_ts_x64.dll`) + ODBC Driver 18, lalu aktifkan di `php.ini`.
- Queue worker: jalankan `php artisan queue:work` sebagai Windows Service (mis. dengan **NSSM**).
- Scheduler: Task Scheduler, setiap 1 menit menjalankan `php C:\path\artisan schedule:run`.
- **Jangan** menjalankan `npm run dev` / `composer dev` di server production.

## 10. Ringkasan Perintah

```bash
# Pertama kali
composer install --no-dev --optimize-autoloader
npm ci && npm run build && rm -f public/hot
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=MenuSeeder --force
php artisan storage:link
php artisan optimize && php artisan scramble:cache

# Setiap update
./deploy.sh
```
