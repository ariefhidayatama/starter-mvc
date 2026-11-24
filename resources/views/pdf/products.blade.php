@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Product> $products */
    $money = fn ($v) => 'IDR '.number_format((float) $v, 0, '.', ',');
    $totalStock = $products->sum('stock');
    $totalValue = $products->sum(fn ($p) => (float) $p->price * $p->stock);

    // Identitas perusahaan (dummy). Ganti sesuai data asli.
    $company = [
        'name' => config('app.name'),
        'tagline' => 'Product Catalog & Inventory',
        'address' => 'Jl. Contoh Raya No. 123, Jakarta 12345',
        'contact' => 'info@example.com · +62 21 0000 0000',
    ];

    // Blok informasi dokumen (DUMMY / contoh). Nanti bisa diisi dari data
    // pelanggan / pesanan sungguhan. Nilai multi-baris: pisahkan dengan "\n".
    $customer = "PT. CONTOH SENTOSA ABADI Tbk.\nJL. RAYA INDUSTRI NO. 88 KAV. 12\nTANGERANG 15710";
    $infoLeft = [
        'Bill to' => $customer,
        'Attention' => 'Bpk. Andi Pratama (Purchasing)',
        'Phone no' => '021-5550 1234',
        'Email' => 'purchasing@contoh-sentosa.test',
    ];
    $infoRight = [
        'Doc date' => $generatedAt->format('d F Y'),
        'Doc number' => $documentNo,
        'Cust PO no' => 'PO-2026-001234',
        'Payment term' => '30 DAYS',
        'DO no' => 'DO/2026/11/00087',
        'Deliver to' => $customer,
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Products</title>
    <style>
        /* Margin halaman memberi ruang untuk header (atas) & footer (bawah)
           yang position: fixed, sehingga berulang di setiap halaman. */
        @page { margin: 112px 32px 64px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }

        /* ---------- Header ---------- */
        header { position: fixed; top: -92px; left: 0; right: 0; height: 76px; }
        header table { width: 100%; border-collapse: collapse; }
        header td { padding: 0; vertical-align: middle; border: none; background: none; }
        .logo { width: 48px; height: 48px; }
        .brand { padding-left: 12px; }
        .brand-name { font-size: 16px; font-weight: bold; color: #111827; }
        .brand-tagline { font-size: 9px; color: #4f46e5; margin-top: 1px; }
        .brand-address { font-size: 8px; color: #6b7280; margin-top: 3px; }
        .doc { text-align: right; }
        .doc-title { font-size: 15px; font-weight: bold; color: #111827; letter-spacing: .02em; }
        .doc-meta { font-size: 8px; color: #6b7280; margin-top: 3px; line-height: 1.5; }
        .header-rule { position: fixed; top: -14px; left: 0; right: 0; height: 3px;
                       background: #4f46e5; border-bottom: 1px solid #c7d2fe; }

        /* ---------- Footer ---------- */
        footer { position: fixed; bottom: -46px; left: 0; right: 0; height: 30px;
                 border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #6b7280; }
        /* "Page X of Y" digambar ProductPdfBuilder di sisi kanan footer. */

        /* ---------- Isi ---------- */
        /* Blok info dokumen: dua kolom "LABEL : nilai" (Bill to / Doc date ...) */
        table.info { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        td.info-col { width: 50%; vertical-align: top; padding: 0; }
        table.info-rows { width: 100%; border-collapse: collapse; }
        table.info-rows td { padding: 1.5px 0; vertical-align: top; font-size: 9px; }
        td.info-label { width: 90px; font-weight: bold; color: #111827; white-space: nowrap; }
        td.info-colon { width: 12px; color: #111827; }
        td.info-value { color: #1f2937; }
        .info-strong { font-weight: bold; color: #111827; }
        table.data { width: 100%; border-collapse: collapse; }
        /* Portrait: font & padding lebih rapat agar 8 kolom muat. */
        table.data th { background: #f3f4f6; text-align: left; font-size: 8px; text-transform: uppercase;
             letter-spacing: .02em; color: #4b5563; padding: 6px 5px; border-bottom: 1px solid #d1d5db; }
        table.data td { padding: 5px 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; font-size: 9px; }
        table.data tbody tr:nth-child(even) td { background: #fafafa; }
        table.data thead { display: table-header-group; } /* ulangi judul kolom tiap halaman */
        table.data tr { page-break-inside: avoid; }
        table.data .num { text-align: right; white-space: nowrap; }
        .sku { font-family: DejaVu Sans Mono, monospace; color: #6b7280; }
        .badge { padding: 1px 5px; border-radius: 8px; font-size: 8px; }
        .active { background: #dcfce7; color: #166534; }
        .inactive { background: #f3f4f6; color: #4b5563; }
        .out { color: #b45309; }
        table.data tfoot td { font-weight: bold; background: #eef2ff; border-top: 1px solid #c7d2fe; }
    </style>
</head>
<body>
    <header>
        <table>
            <tr>
                <td style="width: 48px">
                    @if ($logo)
                        <img src="{{ $logo }}" class="logo" alt="Logo">
                    @endif
                </td>
                <td class="brand">
                    <div class="brand-name">{{ $company['name'] }}</div>
                    <div class="brand-tagline">{{ $company['tagline'] }}</div>
                    <div class="brand-address">{{ $company['address'] }}<br>{{ $company['contact'] }}</div>
                </td>
                <td class="doc">
                    <div class="doc-title">PRODUCT LIST</div>
                    <div class="doc-meta">
                        No. {{ $documentNo }}<br>
                        Date: {{ $generatedAt->format('d M Y H:i') }}<br>
                        @if ($generatedBy) Prepared by: {{ $generatedBy }} @endif
                    </div>
                </td>
            </tr>
        </table>
    </header>
    <div class="header-rule"></div>

    <footer>
        {{ $company['name'] }} &middot; {{ $documentNo }} &middot;
        Generated by system, valid without signature.
    </footer>

    <div class="content">
        <table class="info">
            <tr>
                @foreach ([$infoLeft, $infoRight] as $side => $rows)
                    <td class="info-col" style="{{ $side === 0 ? 'padding-right: 24px' : 'padding-left: 24px' }}">
                        <table class="info-rows">
                            @foreach ($rows as $label => $value)
                                <tr>
                                    <td class="info-label">{{ strtoupper($label) }}</td>
                                    <td class="info-colon">:</td>
                                    <td class="info-value">
                                        @foreach (explode("\n", $value) as $line)
                                            <div class="{{ $loop->first && str_contains($value, "\n") ? 'info-strong' : '' }}">{{ $line }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                @endforeach
            </tr>
        </table>

        <table class="data">
            <thead>
                <tr>
                    <th style="width: 18px">#</th>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th class="num">Price</th>
                    <th class="num">Stock</th>
                    <th class="num">Stock value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $i => $product)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="sku">{{ $product->sku }}</td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category?->name ?? '-' }}</td>
                        <td class="num">{{ $money($product->price) }}</td>
                        <td class="num {{ $product->stock > 0 ? '' : 'out' }}">
                            {{ $product->stock > 0 ? number_format($product->stock) : 'Out of stock' }}
                        </td>
                        <td class="num">{{ $money((float) $product->price * $product->stock) }}</td>
                        <td>
                            <span class="badge {{ $product->is_active ? 'active' : 'inactive' }}">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">Total</td>
                    <td class="num">{{ number_format($totalStock) }}</td>
                    <td class="num">{{ $money($totalValue) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</body>
</html>
