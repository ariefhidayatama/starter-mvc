<?php

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->admin = User::factory()->create()->assignRole(RoleName::Admin);
    $this->coffee = Category::factory()->create(['name' => 'Coffee']);
});

/** Buat file .xlsx sementara dari array baris (baris pertama = judul). */
function xlsxUpload(array $rows, string $name = 'products.xlsx'): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, $name, null, null, true);
}

it('membuat produk baru dan memperbarui SKU yang sudah ada dari Excel', function () {
    $existing = Product::factory()->for($this->coffee)->create(['sku' => 'KOP-001', 'name' => 'Old name', 'stock' => 1]);

    $file = xlsxUpload([
        ['SKU', 'Product', 'Category', 'Description', 'Price', 'Stock', 'Status'],
        ['kop-001', 'Arabica Updated', 'coffee', '', 15000, 7, ''],
        ['KOP-NEW', 'Robusta 1kg', 'Coffee', 'Strong', '125,000', 3, 'Inactive'],
        [null, null, null, null, null, null, null], // baris kosong diabaikan
    ]);

    $this->actingAs($this->admin)
        ->post('/admin/products/import', ['file' => $file])
        ->assertRedirect('/admin/products')
        ->assertSessionHasNoErrors();

    $existing->refresh();
    expect($existing->name)->toBe('Arabica Updated')
        ->and($existing->stock)->toBe(7)
        ->and($existing->is_active)->toBeTrue(); // status kosong = tidak diubah

    $new = Product::firstWhere('sku', 'KOP-NEW');
    expect($new)->not->toBeNull()
        ->and($new->price)->toBe('125000.00')
        ->and($new->is_active)->toBeFalse()
        ->and($new->category_id)->toBe($this->coffee->id);
});

it('menolak seluruh file jika ada baris salah, dengan error per baris', function () {
    $file = xlsxUpload([
        ['SKU', 'Product', 'Category', 'Price', 'Stock'],
        ['OK-001', 'Valid row', 'Coffee', 1000, 1],
        ['BAD SKU', 'Bad sku', 'Coffee', 1000, 1],
        ['OK-002', 'Unknown category', 'Nope', 1000, 1],
        ['OK-003', 'Negative stock', 'Coffee', 1000, -5],
        ['OK-001', 'Duplicate', 'Coffee', 1000, 1],
    ]);

    $this->actingAs($this->admin)
        ->from('/admin/products')
        ->post('/admin/products/import', ['file' => $file])
        ->assertRedirect('/admin/products')
        ->assertSessionHasErrors(['file', 'rows.0', 'rows.1', 'rows.2', 'rows.3']);

    $errors = session('errors')->getBag('default');
    expect($errors->first('rows.0'))->toStartWith('Row 3:')
        ->and($errors->first('rows.1'))->toContain('Category "Nope" does not exist')
        ->and($errors->first('rows.3'))->toContain('appears more than once');

    expect(Product::count())->toBe(0); // tidak ada yang tersimpan
});

it('bisa mengupload ulang file hasil Export Excel', function () {
    $products = Product::factory()->for($this->coffee)->count(2)->create(['stock' => 5]);

    $export = $this->actingAs($this->admin)
        ->get('/admin/products/excel?'.http_build_query(['ids' => $products->pluck('ulid')->all()]));
    $path = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
    file_put_contents($path, $export->streamedContent());

    $this->actingAs($this->admin)
        ->post('/admin/products/import', ['file' => new UploadedFile($path, 'export.xlsx', null, null, true)])
        ->assertSessionHasNoErrors();

    expect(Product::count())->toBe(2); // diperbarui, bukan diduplikasi
});

it('menerima file CSV', function () {
    $csv = "\xEF\xBB\xBFSKU,Product,Category,Price,Stock,Status\nCSV-001,\"Kopi \"\"Spesial\"\", 1kg\",Coffee,12500.50,4,Active\n";

    $this->actingAs($this->admin)
        ->post('/admin/products/import', ['file' => UploadedFile::fake()->createWithContent('products.csv', $csv)])
        ->assertSessionHasNoErrors();

    $product = Product::firstWhere('sku', 'CSV-001');
    expect($product->name)->toBe('Kopi "Spesial", 1kg')
        ->and($product->price)->toBe('12500.50');
});

it('menolak file tanpa kolom wajib atau tipe salah', function () {
    $this->actingAs($this->admin)->from('/admin/products')
        ->post('/admin/products/import', ['file' => xlsxUpload([['Name', 'Price'], ['A', 1]])])
        ->assertSessionHasErrors('file');

    $this->actingAs($this->admin)->from('/admin/products')
        ->post('/admin/products/import', ['file' => UploadedFile::fake()->create('photo.png', 10, 'image/png')])
        ->assertSessionHasErrors('file');
});

it('menolak import untuk user tanpa izin create/update', function () {
    $customer = User::factory()->create()->assignRole(RoleName::Customer);

    $this->actingAs($customer)
        ->post('/admin/products/import', ['file' => xlsxUpload([['SKU'], ['X-1']])])
        ->assertForbidden();
});

it('mengunduh template import', function () {
    $this->actingAs($this->admin)
        ->get('/admin/products/import/template')
        ->assertOk()
        ->assertDownload('products-import-template.xlsx');
});
