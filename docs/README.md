# Dokumentasi Belajar — Starter MVC (Laravel + Inertia React + REST API)

Folder ini berisi catatan belajar untuk setiap tahap dan fitur yang dibangun di project ini.
Bacalah dokumen sesuai urutan, karena setiap dokumen mengasumsikan Anda sudah memahami dokumen sebelumnya.

## Struktur

```
docs/
├── README.md                     ← Anda di sini
├── 00-arsitektur/                ← Keputusan desain besar (kenapa begini)
│   └── tahap-01-analisis-arsitektur.md
└── fitur/                        ← Satu file per fitur/proses (bagaimana membuatnya)
    ├── _TEMPLATE.md              ← Kerangka yang dipakai semua dokumen fitur
    ├── 01-instalasi-dan-struktur.md
    ├── 02-routing-dan-versioning.md
    ├── 03-auth-api-token.md
    ├── 04-role-permission-policy.md
    ├── 04b-ui-manajemen-akses.md
    ├── 04c-tampilan-dan-tema-ui.md
    ├── 05-standar-response-error.md
    ├── 06-crud-kategori.md
    ├── 07-crud-produk.md
    ├── 08-data-table-react.md
    ├── 08b-manajemen-menu.md
    ├── 08c-access-control-dan-visibilitas-menu.md
    ├── 08d-crud-generator.md
    ├── 08e-integrasi-erp-sql-server.md
    ├── 08f-crud-master-multi-detail.md
    ├── 08g-url-bersih-session-dan-ulid.md
    ├── 08h-generator-rest-api-dan-rbac.md
    └── 09-deploy-production.md
```

## Akun Demo

Setelah `php artisan migrate:fresh --seed` (password semua akun: `password`):

| Email                  | Role     |
| ---------------------- | -------- |
| `admin@example.com`    | admin    |
| `staff@example.com`    | staff    |
| `customer@example.com` | customer |

## Urutan Baca

| #   | Dokumen                                                                                           | Status    |
| --- | ------------------------------------------------------------------------------------------------- | --------- |
| 0   | [Tahap 1 — Analisis & Arsitektur](00-arsitektur/tahap-01-analisis-arsitektur.md)                  | Disetujui |
| 1   | [Instalasi & struktur folder](fitur/01-instalasi-dan-struktur.md)                                 | Selesai   |
| 2   | [Routing & versioning API](fitur/02-routing-dan-versioning.md)                                    | Selesai   |
| 3   | [Auth API token (Sanctum, multi-device)](fitur/03-auth-api-token.md)                              | Selesai   |
| 4   | [Role, permission & policy](fitur/04-role-permission-policy.md)                                   | Selesai   |
| 4b  | [UI manajemen Users, Roles & Permissions](fitur/04b-ui-manajemen-akses.md)                        | Selesai   |
| 4c  | [Tampilan modern & sistem tema UI](fitur/04c-tampilan-dan-tema-ui.md)                             | Selesai   |
| 5   | [Standar response & error handling](fitur/05-standar-response-error.md)                           | Selesai   |
| 6   | [CRUD Kategori (Web + API)](fitur/06-crud-kategori.md)                                            | Selesai   |
| 7   | [CRUD Produk (Web + API)](fitur/07-crud-produk.md)                                                | Selesai   |
| 8   | [Data Table di React (TanStack v9 + shadcn/ui)](fitur/08-data-table-react.md)                     | Selesai   |
| 8b  | [Manajemen menu & submenu (dashboard + website)](fitur/08b-manajemen-menu.md)                     | Selesai   |
| 8c  | [Access control, menu per role & drag and drop](fitur/08c-access-control-dan-visibilitas-menu.md) | Selesai   |
| 8d  | [CRUD generator (dari menu & terminal)](fitur/08d-crud-generator.md)                              | Selesai   |
| 8e  | [Integrasi ERP SQL Server & CRUD master–detail](fitur/08e-integrasi-erp-sql-server.md)            | Selesai   |
| 8f  | [CRUD master–detail: banyak tabel detail & satu transaksi](fitur/08f-crud-master-multi-detail.md) | Selesai   |
| 8h  | [Generator REST API & RBAC API](fitur/08h-generator-rest-api-dan-rbac.md)                         | Fase 1    |
| 9   | Optimasi query, index, pagination                                                                 | Belum     |
| 10  | Checkout & database transaction                                                                   | Belum     |
| 11  | Raw SQL & stored procedure                                                                        | Belum     |
| 12  | Redis cache                                                                                       | Belum     |
| 13  | Queue & background job                                                                            | Belum     |
| 14  | Dokumentasi API (OpenAPI)                                                                         | Belum     |
| 15  | Load test dengan k6                                                                               | Belum     |
| 16  | Laravel Octane                                                                                    | Belum     |

## Tips Membaca Diagram

Diagram ditulis dengan **Mermaid**. Di VS Code, pasang ekstensi _Markdown Preview Mermaid Support_, lalu buka preview (`Ctrl+Shift+V`).
