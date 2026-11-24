# 04c — Tampilan Modern & Sistem Tema UI

> Tahap: 3 (penyempurnaan) · Dibuat: 2026-11-11 · Status: selesai

## 1. Tujuan

Mengubah tampilan bawaan starter kit (hitam-putih polos) menjadi lebih modern dan **konsisten**: satu warna brand, satu font, dan komponen yang bisa dipakai ulang di semua halaman, termasuk fitur yang akan dibuat di tahap berikutnya.

## 2. Konsep yang Perlu Dipahami

### Design token (CSS variable)

Komponen shadcn/ui tidak menulis warna langsung (`bg-indigo-600`). Komponen memakai **nama peran**: `bg-primary`, `text-muted-foreground`, `border-border`. Nilai sebenarnya disimpan di CSS variable dalam `resources/css/app.css`:

```css
:root {
    --primary: oklch(0.511 0.262 276.966); /* indigo */
}
.dark {
    --primary: oklch(
        0.673 0.182 276.935
    ); /* indigo lebih terang untuk latar gelap */
}
```

Akibatnya: **ganti satu baris di `app.css` → seluruh aplikasi (tombol, link, checkbox, badge, chart) ikut berubah**, termasuk mode gelap.

### Kenapa format `oklch()`?

`oklch(lightness chroma hue)` adalah format warna modern. Mengubah `lightness` menghasilkan warna yang terlihat lebih terang/gelap secara konsisten oleh mata manusia, sehingga mudah membuat variasi terang/gelap dari satu warna.

### Utility kustom Tailwind v4

```css
@utility bg-brand-gradient {
    background-image: linear-gradient(135deg, oklch(...) 0%, oklch(...) 100%);
}
```

Di Tailwind v4, class buatan sendiri **harus** memakai `@utility` (bukan `@layer utilities`) agar bisa digabung dengan varian seperti `hover:`, `group-hover:`, atau `dark:`.

## 3. File yang Terlibat

| File                                                                                | Peran                                                                                    |
| ----------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `resources/css/app.css`                                                             | Token warna terang & gelap, font, radius, utility `bg-brand-gradient` & `bg-dot-pattern` |
| `vite.config.ts`                                                                    | Memuat font **Plus Jakarta Sans** (Bunny Fonts, ramah privasi)                           |
| `resources/views/app.blade.php`                                                     | Warna latar awal (mencegah kedipan putih saat mode gelap)                                |
| `resources/js/components/page-header.tsx`                                           | Judul halaman: ikon + judul + deskripsi + tombol aksi                                    |
| `resources/js/components/user-avatar.tsx`                                           | Avatar inisial berwarna (`UserAvatar`, `UserIdentity`)                                   |
| `resources/js/components/status-badge.tsx`                                          | Badge pil berwarna + `roleTone()`                                                        |
| `resources/js/components/empty-state.tsx`                                           | Tampilan saat data kosong                                                                |
| `resources/js/components/theme-toggle.tsx`                                          | Tombol ganti tema terang/gelap di header                                                 |
| `resources/js/components/pagination.tsx`                                            | Pagination bergaya modern                                                                |
| `resources/js/components/ui/table.tsx`                                              | Tabel: header abu-abu, huruf kapital kecil, baris lebih lega                             |
| `resources/js/lib/format.ts`                                                        | Format tanggal Indonesia (`10 Nov 2026`, `3 hari yang lalu`)                             |
| `resources/js/layouts/auth/auth-split-layout.tsx`                                   | Halaman login/register dua kolom dengan panel gradien                                    |
| `resources/js/pages/welcome.tsx`                                                    | Landing page baru                                                                        |
| `resources/js/pages/dashboard.tsx` + `app/Http/Controllers/DashboardController.php` | Dashboard dengan statistik nyata                                                         |

## 4. Langkah Pembuatan

### Langkah 1 — Token warna & font

Di `app.css`, nilai `--primary`, `--accent`, `--sidebar-*`, dan seterusnya diganti ke palet indigo dengan netral abu-abu kebiruan, baik untuk `:root` (terang) maupun `.dark` (gelap). Dua token baru ditambahkan untuk status:

```css
--success: oklch(0.627 0.17 149.214); /* hijau: "Aktif" */
--warning: oklch(0.769 0.165 70.08); /* kuning: peringatan */
```

dan didaftarkan ke Tailwind agar bisa dipakai sebagai class `bg-success`, `text-warning`:

```css
@theme {
    --color-success: var(--success);
    --color-warning: var(--warning);
}
```

Font diganti di dua tempat:

```ts
// vite.config.ts
bunny('Plus Jakarta Sans', { weights: [400, 500, 600, 700, 800] }),
```

```css
/* app.css */
--font-sans: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif, ...;
```

### Langkah 2 — Komponen yang dipakai ulang

Setiap halaman admin baru (Kategori, Produk, dll. di Tahap 4) cukup memakai:

```tsx
<PageHeader
    icon={Package}
    title="Produk"
    description="Kelola katalog produk."
    actions={<Button>Tambah produk</Button>}
/>

<Card className="gap-0 overflow-hidden py-0">
    {/* toolbar filter */}
    <Table>...</Table>
    <div className="border-t px-4 py-3"><Pagination page={products} /></div>
</Card>

<StatusBadge tone="success">Aktif</StatusBadge>
<UserIdentity name={user.name} email={user.email} />
<EmptyState icon={PackageX} title="Belum ada produk" />
```

Dengan pola ini, semua halaman otomatis terlihat seragam.

### Langkah 3 — Dashboard dengan data nyata

`routes/web.php` sebelumnya memakai `Route::inertia('dashboard', 'dashboard')` (halaman statis tanpa data). Sekarang diganti ke controller:

```php
Route::get('dashboard', DashboardController::class)->name('dashboard');
```

```php
'stats' => $user->can(PermissionName::UsersView) ? $this->stats() : null,
```

- Statistik user **hanya dihitung dan dikirim** jika user berhak (`users.view`). Customer tidak menerima data user lain sama sekali, bukan sekadar disembunyikan di React.
- Tiga angka (total, aktif, baru minggu ini) dihitung dalam **satu query agregat**:

```php
DB::table('users')
    ->selectRaw('COUNT(*) AS total')
    ->selectRaw('SUM(is_active = 1) AS active')
    ->selectRaw('SUM(created_at >= ?) AS new_this_week', [now()->subDays(7)])
    ->first();
```

Tiga `COUNT` terpisah berarti tiga kali membaca tabel. Teknik `SUM(kondisi)` ini menghitung semuanya dalam satu kali baca.

### Langkah 4 — Layout login split

`resources/js/layouts/auth-layout.tsx` cukup mengganti template yang diimpor:

```tsx
import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout';
```

Starter kit menyediakan tiga template (`auth-simple-layout`, `auth-card-layout`, `auth-split-layout`). Kita memakai versi split yang sudah didesain ulang.

## 5. Mengganti Warna Brand

Misalnya ingin hijau tosca, bukan indigo. Ubah di `app.css`:

| Variabel                                                               | Fungsi                                         |
| ---------------------------------------------------------------------- | ---------------------------------------------- |
| `--primary` (`:root` & `.dark`)                                        | Tombol utama, link, checkbox, pagination aktif |
| `--ring`                                                               | Garis fokus input                              |
| `--accent`, `--accent-foreground`                                      | Latar hover/aktif lembut                       |
| `--sidebar-primary`, `--sidebar-accent`, `--sidebar-accent-foreground` | Menu sidebar aktif                             |
| `@utility bg-brand-gradient`                                           | Gradien logo, hero, panel login                |

Contoh: hue indigo `277` → tosca sekitar `185`, misalnya `--primary: oklch(0.6 0.12 185);`. Setelah itu jalankan `npm run dev`, lalu periksa mode terang **dan** gelap.

## 6. Cara Mencoba

```bash
php artisan migrate:fresh --seed
composer run dev
```

1. Buka `http://localhost:8000` → landing page baru.
2. Klik **Masuk** → halaman login split.
3. Login sebagai `admin@example.com` → dashboard statistik.
4. Klik ikon bulan/matahari di kanan atas → ganti tema.
5. Login sebagai `customer@example.com` → dashboard berisi ringkasan akun (tanpa statistik user).

## 7. Kesalahan Umum & Cara Mengatasinya

| Gejala                                           | Penyebab                                                    | Solusi                                               |
| ------------------------------------------------ | ----------------------------------------------------------- | ---------------------------------------------------- |
| Class kustom tidak bekerja dengan `hover:`       | Didefinisikan di `@layer utilities`                         | Gunakan `@utility nama { ... }`                      |
| Font masih font lama                             | Cache browser / Vite belum restart                          | Restart `composer run dev`, hard refresh (`Ctrl+F5`) |
| Kedipan putih saat membuka halaman di mode gelap | Warna `html.dark` di `app.blade.php` tidak sama dengan tema | Samakan dengan `--sidebar` di `.dark`                |
| Warna teks sulit dibaca di mode gelap            | Hanya mengubah `:root`, lupa `.dark`                        | Selalu ubah keduanya                                 |
| Halaman baru terlihat beda sendiri               | Tidak memakai komponen bersama                              | Gunakan `PageHeader`, `Card`, `StatusBadge`, dst.    |

## 8. Latihan

1. Ganti warna brand ke hijau tosca (lihat bagian 5), lalu kembalikan lagi.
2. Tambahkan kartu statistik kelima di dashboard: "User belum verifikasi email".
3. Pakai `EmptyState` di halaman Roles saat tidak ada role (coba hapus semua role non-sistem).

## 9. Referensi

- shadcn/ui theming: https://ui.shadcn.com/docs/theming
- Tailwind CSS v4 `@theme` & `@utility`: https://tailwindcss.com/docs/theme
- OKLCH color picker: https://oklch.com
