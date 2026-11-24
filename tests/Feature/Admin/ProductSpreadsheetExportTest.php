<?php

use App\Enums\RoleName;
use App\Models\Product;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
});

it('mengunduh Excel berisi produk yang dicentang', function () {
    $a = Product::factory()->create(['name' => 'Alpha', 'price' => 10000, 'stock' => 3]);
    $b = Product::factory()->create(['name' => 'Bravo', 'price' => 2500, 'stock' => 4]);
    Product::factory()->create(['name' => 'Not selected']);

    $response = $this->actingAs($this->admin)
        ->get('/admin/products/excel?'.http_build_query(['ids' => [$b->ulid, $a->ulid]]))
        ->assertOk()
        ->assertDownload();

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->streamedContent());
    $sheet = IOFactory::load($path)->getActiveSheet();
    unlink($path);

    // Baris 4 = judul kolom, data mulai baris 5 (urut nama), lalu Total.
    expect($sheet->getCell('C5')->getValue())->toBe('Alpha')
        ->and($sheet->getCell('C6')->getValue())->toBe('Bravo')
        ->and($sheet->getCell('E5')->getValue())->toEqual(10000)
        ->and($sheet->getCell('G5')->getCalculatedValue())->toEqual(30000)
        ->and($sheet->getCell('A7')->getValue())->toBe('Total')
        ->and($sheet->getCell('G7')->getCalculatedValue())->toEqual(40000)
        ->and($sheet->getHighestRow())->toBe(7);
});

it('mengunduh CSV berisi produk yang dicentang', function () {
    $a = Product::factory()->create(['sku' => 'ABC-001', 'name' => 'Kopi "Spesial", 1kg', 'price' => 12500, 'stock' => 2]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/products/csv?ids[]='.$a->ulid)
        ->assertOk()
        ->assertDownload();

    $lines = preg_split('/\R/', trim(substr($response->streamedContent(), 3))); // tanpa BOM
    $rows = array_map(fn ($l) => str_getcsv($l, escape: ''), $lines);

    expect($rows[0])->toBe(['No', 'SKU', 'Product', 'Category', 'Price', 'Stock', 'Stock value', 'Status'])
        ->and($rows[1][1])->toBe('ABC-001')
        ->and($rows[1][2])->toBe('Kopi "Spesial", 1kg')
        ->and($rows[1][4])->toBe('12500.00')
        ->and($rows[1][6])->toBe('25000.00');
});

it('menolak ekspor tanpa pilihan & tanpa izin', function () {
    $this->actingAs($this->admin)->from('/admin/products')
        ->get('/admin/products/excel')->assertSessionHasErrors('ids');
    $this->actingAs($this->admin)->from('/admin/products')
        ->get('/admin/products/csv')->assertSessionHasErrors('ids');

    $customer = User::factory()->create()->assignRole(RoleName::Customer);
    $product = Product::factory()->create();

    $this->actingAs($customer)->get('/admin/products/excel?ids[]='.$product->ulid)->assertForbidden();
    $this->actingAs($customer)->get('/admin/products/csv?ids[]='.$product->ulid)->assertForbidden();
});
