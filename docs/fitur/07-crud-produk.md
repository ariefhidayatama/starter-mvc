# 07 — CRUD Produk: Search, Filter, Sort & Pagination (Web + API)

> Tahap: 4 · Dibuat: 2026-11-11 · Status: selesai

## 1. Tujuan

CRUD produk dengan daftar yang bisa dicari, difilter, diurutkan, dan dipaginasi, dengan aturan yang **sama** untuk halaman admin dan API mobile. Fitur ini juga memperkenalkan:

- **Katalog publik**: tamu/customer hanya melihat produk aktif.
- **Whitelist** parameter query (keamanan).
- **Cursor pagination** untuk infinite scroll.
- **Update sebagian** (PATCH).

## 2. Konsep yang Perlu Dipahami

### Kenapa query string di-whitelist?

Bayangkan kode naif:

```php
$query->orderBy($request->input('sort'));     // ❌ BAHAYA
```

Klien bisa mengirim `?sort=password` (mengurutkan berdasarkan kolom rahasia), atau kolom tanpa index yang membuat database bekerja keras. Karena itu **setiap** parameter divalidasi dengan daftar nilai yang diizinkan:

```php
'sort' => ['nullable', Rule::in(['name', '-name', 'price', '-price', 'stock', '-stock', 'created_at', '-created_at'])],
'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
```

Lalu `App\Support\QuerySort` memetakan kunci sort → nama kolom. Input user **tidak pernah** dipakai langsung sebagai nama kolom.

### Kenapa harga disimpan `DECIMAL` dan dikirim sebagai string?

```js
0.1 + 0.2; // 0.30000000000000004 (float)
```

Untuk uang, galat sekecil apa pun tidak bisa diterima. Database memakai `DECIMAL(15,2)`, model memakai cast `decimal:2`, dan JSON mengirim `"price": "85000.00"` (string). Klien memformat sendiri untuk tampilan.

### Produk nonaktif: siapa yang boleh melihat?

| Pengunjung                      | Daftar `/api/v1/products` | Detail produk nonaktif                                  |
| ------------------------------- | ------------------------- | ------------------------------------------------------- |
| Tamu / customer                 | Hanya aktif               | **404** (bukan 403, agar keberadaannya tidak terungkap) |
| Staff / admin (`products.view`) | Semua + filter status     | Terlihat                                                |

~~Endpoint GET bersifat publik (tanpa middleware `auth`).~~

> **Pembaruan (8h, 2026-11-24):** semua endpoint produk kini **wajib token** + permission `api.access`
> (lihat [8h](08h-generator-rest-api-dan-rbac.md)). Produk aktif tetap bisa dibaca semua user API
> (mis. customer); produk nonaktif hanya untuk `products.view`.

## 3. File yang Terlibat

| File                                                        | Peran                                   |
| ----------------------------------------------------------- | --------------------------------------- |
| `database/migrations/..._create_products_table.php`         | Tabel `products`                        |
| `app/Models/Product.php`                                    | Model, cast, relasi, scope `active()`   |
| `app/Policies/ProductPolicy.php`                            | `view()` + `canSeeInactive()`           |
| `app/Http/Requests/Catalog/IndexProductRequest.php`         | Whitelist search/filter/sort/pagination |
| `app/Http/Requests/Catalog/SaveProductRequest.php`          | Validasi tambah & ubah (POST/PUT/PATCH) |
| `app/Support/QuerySort.php`                                 | Menerapkan `?sort=` dengan aman         |
| `app/Services/Catalog/ProductQueryService.php`              | `paginate()` & `cursor()`               |
| `app/Actions/Product/*.php`                                 | Create / Update / Delete                |
| `app/Http/Resources/V1/ProductResource.php`                 | Bentuk JSON (API & Web)                 |
| `app/Http/Controllers/{Admin,Api/V1}/ProductController.php` | Controller Web & API                    |
| `database/seeders/CatalogSeeder.php`                        | 8 kategori, 160 produk                  |

## 4. Langkah Pembuatan

### Langkah 1 — Migration

```php
$table->foreignId('category_id')->constrained()->restrictOnDelete();
$table->string('sku', 50)->unique();
$table->string('name', 150);
$table->text('description')->nullable();
$table->decimal('price', 15, 2);           // uang: DECIMAL, bukan FLOAT
$table->unsignedInteger('stock')->default(0);
$table->boolean('is_active')->default(true);
$table->timestamps();
$table->index(['is_active', 'category_id']);
```

Index lengkap (untuk search, sort, filter harga) **dirancang dan diukur di Tahap 5** dengan 100.000 produk, karena index tanpa pengukuran hanya tebakan.

### Langkah 2 — Validasi yang mendukung PATCH

```php
public function rules(): array
{
    $product = $this->product();
    $required = $product ? 'sometimes' : 'required';   // ubah: hanya yang dikirim

    return [
        'category_id' => [$required, 'integer', Rule::exists('categories', 'id')],
        'sku' => [$required, 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('products', 'sku')->ignore($product?->id)],
        'price' => [$required, 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
        'stock' => [$required, 'integer', 'min:0', 'max:1000000'],
        // ...
    ];
}
```

Dengan `sometimes`, aplikasi gudang cukup mengirim:

```http
PATCH /api/v1/products/15
{ "stock": 50 }
```

tanpa harus mengirim ulang nama, harga, dan seterusnya.

### Langkah 3 — Query service

```php
$query = Product::query()
    ->select([...kolom yang ditampilkan...])          // tanpa description (teks panjang)
    ->with('category:id,name,slug')                  // eager loading → anti N+1
    ->when(! $canSeeInactive, fn ($q) => $q->where('is_active', true))
    ->when($search, fn ($q, $s) => $q->where(fn ($q) => $q
        ->where('name', 'like', "{$s}%")
        ->orWhere('sku', 'like', strtoupper($s).'%')))
    ->when($categoryId, fn ($q, $id) => $q->where('category_id', $id))
    ->when($priceMin !== null, fn ($q) => $q->where('price', '>=', $priceMin))
    // ...

QuerySort::apply($query, $sort, self::SORTS, '-created_at');
```

> **Kurung pada OR.** `->where(fn ($q) => $q->where(A)->orWhere(B))` menghasilkan `... AND (A OR B)`. Tanpa kurung, hasilnya `... AND A OR B`, sehingga produk **nonaktif** yang cocok dengan B ikut tampil ke publik.

`QuerySort` selalu menambahkan urutan kedua berdasarkan `id`. Tanpanya, produk dengan harga sama bisa muncul di dua halaman berbeda, dan cursor pagination tidak bisa bekerja.

### Langkah 4 — Resource dengan relasi bersarang

```php
'category' => $this->whenLoaded(
    'category',
    fn () => CategoryResource::make($this->category)->resolve($request),
),
```

> **Bug nyata yang ditemukan saat pengembangan:** awalnya ditulis `CategoryResource::make($this->whenLoaded('category'))`. API berjalan benar, tetapi di halaman web kolom Category kosong. Penyebabnya, Inertia menemukan objek `JsonResource` di dalam props lalu merendernya sebagai response tersendiri yang dibungkus `{ "data": ... }`, sehingga `category.name` di React menjadi `undefined`. Solusinya: resource bersarang di-`resolve()` menjadi array biasa. Test `CatalogManagementTest` sekarang memeriksa `products.data.0.category.name` agar bug ini tidak terulang.

### Langkah 5 — Web memakai ulang Resource API

```php
'products' => $query->paginate($filters, $user)
    ->through(fn (Product $p) => ProductResource::make($p)->resolve($request)),
```

`through()` mengubah setiap item di dalam paginator tanpa menghilangkan info halaman (`current_page`, `links`, ...). Bentuk data produk hanya didefinisikan **sekali** di `ProductResource`.

## 5. Endpoint API

| Method    | URL                     | Auth                       |
| --------- | ----------------------- | -------------------------- |
| GET       | `/api/v1/products`      | Bearer + `api.access`      |
| GET       | `/api/v1/products/{id}` | Bearer + `api.access`      |
| POST      | `/api/v1/products`      | Bearer + `products.create` |
| PUT/PATCH | `/api/v1/products/{id}` | Bearer + `products.update` |
| DELETE    | `/api/v1/products/{id}` | Bearer + `products.delete` |

Parameter daftar produk:

| Parameter                                 | Contoh                | Keterangan                                                    |
| ----------------------------------------- | --------------------- | ------------------------------------------------------------- |
| `search`                                  | `kopi`                | Awalan nama, atau awalan SKU                                  |
| `filter[category_id]`                     | `2`                   |                                                               |
| `filter[status]`                          | `active` / `inactive` | Hanya berlaku untuk `products.view`                           |
| `filter[price_min]` / `filter[price_max]` | `10000`               |                                                               |
| `filter[in_stock]`                        | `1`                   | Stok > 0                                                      |
| `sort`                                    | `-price`              | `name`, `price`, `stock`, `created_at`                        |
| `per_page`                                | `25`                  | 10 / 25 / 50 / 100                                            |
| `pagination`                              | `cursor`              | Infinite scroll; lanjutkan dengan `cursor=<meta.next_cursor>` |

## 6. Cara Mencoba

```bash
curl "http://localhost:8000/api/v1/products?search=arab&filter[in_stock]=1&sort=-price"
curl "http://localhost:8000/api/v1/products?pagination=cursor&per_page=10"
```

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"staff@example.com","password":"password","device_name":"cli"}' | jq -r .data.access_token)

curl -X PATCH http://localhost:8000/api/v1/products/1 \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"stock": 50}'
```

## 7. Kesalahan Umum

| Gejala                                     | Penyebab                                        | Solusi                                                             |
| ------------------------------------------ | ----------------------------------------------- | ------------------------------------------------------------------ |
| Produk nonaktif terlihat publik            | `orWhere` tanpa kurung                          | Bungkus dengan `where(fn ...)`                                     |
| Kolom kategori kosong di web, benar di API | Objek `JsonResource` bersarang di props Inertia | `->resolve($request)`                                              |
| Produk muncul dobel di halaman berbeda     | Sort tidak unik                                 | `QuerySort` menambah `id` sebagai penentu                          |
| Harga `85000` jadi `84999.99`              | Memakai float                                   | `DECIMAL` + cast `decimal:2` + kirim string                        |
| `422` saat sort kolom tertentu             | Kolom tidak di whitelist                        | Tambahkan ke `ProductQueryService::SORTS` (dan pikirkan index-nya) |

## 8. Latihan

1. Tambahkan filter `filter[sku_prefix]=KOP`.
2. Buat test: produk nonaktif yang dicari lewat `?search=` tidak muncul untuk tamu.
