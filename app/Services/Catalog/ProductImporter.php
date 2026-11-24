<?php

namespace App\Services\Catalog;

use App\Actions\Product\CreateProductAction;
use App\Actions\Product\UpdateProductAction;
use App\Enums\PermissionName;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Upload produk dari Excel (.xlsx / .xls) atau CSV.
 *
 * - Baris judul dicari otomatis (kolom "SKU" di 10 baris pertama), jadi file
 *   hasil "Export Excel" juga bisa diupload ulang. Kolom lain diabaikan.
 * - SKU belum ada  → produk BARU (butuh products.create).
 *   SKU sudah ada  → produk DIPERBARUI (butuh products.update).
 * - Semua-atau-tidak-sama-sekali: satu baris salah → tidak ada yang disimpan,
 *   dan setiap kesalahan dilaporkan per nomor baris Excel.
 */
class ProductImporter
{
    public const MAX_ROWS = 1000;

    /** Jumlah pesan error per baris yang dikirim balik ke layar. */
    public const MAX_REPORTED_ERRORS = 20;

    /** Judul kolom yang dikenali (huruf kecil) → field. */
    private const COLUMNS = [
        'sku' => 'sku',
        'product' => 'name',
        'name' => 'name',
        'product name' => 'name',
        'category' => 'category',
        'description' => 'description',
        'price' => 'price',
        'stock' => 'stock',
        'status' => 'status',
    ];

    /** Kolom template (urutan tampil). */
    private const TEMPLATE_HEADINGS = ['SKU', 'Product', 'Category', 'Description', 'Price', 'Stock', 'Status'];

    public function __construct(
        private readonly CreateProductAction $createProduct,
        private readonly UpdateProductAction $updateProduct,
    ) {}

    /**
     * @return array{created: int, updated: int}
     *
     * @throws ValidationException
     */
    public function import(UploadedFile $file, User $actor): array
    {
        $rows = $this->readRows($file);
        $categories = Category::query()->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim((string) $name)) => (int) $id]);
        $existing = Product::query()
            ->whereIn('sku', array_filter(array_column($rows, 'sku')))
            ->get()
            ->keyBy('sku');
        $canCreate = $actor->can(PermissionName::ProductsCreate);
        $canUpdate = $actor->can(PermissionName::ProductsUpdate);

        $errors = [];
        $valid = [];
        $seen = [];

        foreach ($rows as $row) {
            $line = $row['line'];
            $product = $existing->get($row['sku']);
            [$data, $rowErrors] = $this->validateRow($row, $categories->all(), $product !== null);

            if ($row['sku'] !== '' && isset($seen[$row['sku']])) {
                $rowErrors[] = "SKU {$row['sku']} appears more than once (also on row {$seen[$row['sku']]}).";
            }
            $seen[$row['sku']] ??= $line;

            if ($product && ! $canUpdate) {
                $rowErrors[] = "SKU {$row['sku']} already exists and you are not allowed to update products.";
            } elseif (! $product && ! $canCreate) {
                $rowErrors[] = 'You are not allowed to create products.';
            }

            foreach ($rowErrors as $message) {
                $errors[] = "Row {$line}: {$message}";
            }

            if ($rowErrors === []) {
                $valid[] = ['product' => $product, 'data' => $data];
            }
        }

        if ($errors !== []) {
            $this->fail($errors, count($rows));
        }

        return DB::transaction(function () use ($valid): array {
            $created = 0;
            $updated = 0;

            foreach ($valid as ['product' => $product, 'data' => $data]) {
                if ($product) {
                    $this->updateProduct->execute($product, $data);
                    $updated++;
                } else {
                    /** @var array{category_id: int, sku: string, name: string, description?: string|null, price: numeric-string|int|float, stock: int, is_active?: bool} $data */
                    $this->createProduct->execute($data);
                    $created++;
                }
            }

            return ['created' => $created, 'updated' => $updated];
        });
    }

    /**
     * Template kosong + contoh, dengan sheet "Categories" berisi nama kategori valid.
     */
    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products');
        $sheet->fromArray(self::TEMPLATE_HEADINGS, null, 'A1');
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
        ]);

        $categoryNames = Category::query()->orderBy('name')->pluck('name')->all();
        $example = $categoryNames[0] ?? 'Coffee';
        $sheet->fromArray([
            ['SAMPLE-001', 'Sample Product 250g', $example, 'Optional description', 125000, 50, 'Active'],
            ['SAMPLE-002', 'Sample Product 1kg', $example, '', 450000, 0, 'Inactive'],
        ], null, 'A2');
        $sheet->getStyle('E2:E1000')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A2:A1000')->getNumberFormat()->setFormatCode('@'); // SKU = teks
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Categories');
        $notes->setCellValue('A1', 'Valid category names');
        $notes->getStyle('A1')->getFont()->setBold(true);
        foreach ($categoryNames as $i => $name) {
            $notes->setCellValueExplicit('A'.($i + 2), $name, DataType::TYPE_STRING);
        }
        $notes->setCellValue('C1', 'Notes');
        $notes->getStyle('C1')->getFont()->setBold(true);
        $notes->fromArray([
            ['SKU: letters, numbers, hyphens. An existing SKU UPDATES that product.'],
            ['Category: must match a name in column A (not case-sensitive).'],
            ['Price: plain number, max 2 decimals (e.g. 125000 or 12500.50).'],
            ['Stock: whole number ≥ 0.'],
            ['Status: Active / Inactive (empty = Active for new products).'],
            ['Max '.self::MAX_ROWS.' rows per upload. Rows with errors are reported; nothing is saved until all rows are valid.'],
        ], null, 'C2');
        $notes->getColumnDimension('A')->setAutoSize(true);
        $notes->getColumnDimension('C')->setAutoSize(true);
        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'products-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Baca file → baris data (tanpa baris kosong / judul / Total).
     *
     * @return list<array{line: int, sku: string, name: mixed, category: mixed, description: mixed, price: mixed, stock: mixed, status: mixed}>
     *
     * @throws ValidationException
     */
    private function readRows(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            // Hitung rumus, tanpa format tampilan (angka tetap angka).
            $raw = $spreadsheet->getSheet(0)->toArray(null, true, false, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'The file could not be read. Upload a valid .xlsx, .xls, or .csv file.']);
        }

        // Cari baris judul (yang punya kolom "SKU").
        $headerIndex = null;
        $map = [];
        foreach (array_slice($raw, 0, 10, true) as $i => $cells) {
            $candidate = [];
            foreach ($cells as $col => $value) {
                $key = mb_strtolower(trim((string) $value));
                if (isset(self::COLUMNS[$key]) && ! in_array(self::COLUMNS[$key], $candidate, true)) {
                    $candidate[$col] = self::COLUMNS[$key];
                }
            }
            if (in_array('sku', $candidate, true)) {
                $headerIndex = $i;
                $map = $candidate;
                break;
            }
        }

        if ($headerIndex === null) {
            throw ValidationException::withMessages(['file' => 'No header row found. The first rows must contain the column titles (SKU, Product, Category, Price, Stock, ...). Download the template for the expected format.']);
        }

        $missing = array_diff(['sku', 'name', 'category', 'price', 'stock'], $map);
        if ($missing !== []) {
            $labels = ['sku' => 'SKU', 'name' => 'Product', 'category' => 'Category', 'price' => 'Price', 'stock' => 'Stock'];
            throw ValidationException::withMessages(['file' => 'Missing column(s): '.implode(', ', array_map(fn ($f) => $labels[$f], $missing)).'.']);
        }

        $rows = [];
        foreach (array_slice($raw, $headerIndex + 1, null, true) as $i => $cells) {
            $row = ['line' => $i + 1, 'sku' => '', 'name' => null, 'category' => null, 'description' => null, 'price' => null, 'stock' => null, 'status' => null];
            foreach ($map as $col => $field) {
                $value = $cells[$col] ?? null;
                $row[$field] = is_string($value) ? trim($value) : $value;
            }
            $row['sku'] = strtoupper(trim((string) $row['sku']));

            $isEmpty = collect($map)->every(fn ($field) => $row[$field] === null || $row[$field] === '');
            // Baris "Total" dari file hasil export.
            $isTotal = $row['sku'] === '' && collect($cells)->contains(fn ($v) => is_string($v) && strcasecmp(trim($v), 'Total') === 0);
            if ($isEmpty || $isTotal) {
                continue;
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The file has no product rows.']);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Too many rows ('.count($rows).'). The maximum is '.self::MAX_ROWS.' per upload.']);
        }

        return $rows;
    }

    /**
     * @param  array{line: int, sku: string, name: mixed, category: mixed, description: mixed, price: mixed, stock: mixed, status: mixed}  $row
     * @param  array<string, int>  $categories  nama kategori (huruf kecil) → id
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validateRow(array $row, array $categories, bool $exists): array
    {
        $errors = [];
        $categoryName = trim((string) $row['category']);
        $categoryId = $categories[mb_strtolower($categoryName)] ?? null;

        if ($categoryName !== '' && $categoryId === null) {
            $errors[] = "Category \"{$categoryName}\" does not exist.";
        }

        $status = mb_strtolower(trim((string) $row['status']));
        $isActive = match ($status) {
            '' => $exists ? null : true,
            'active', 'aktif', '1', 'yes', 'ya', 'true' => true,
            'inactive', 'nonaktif', 'non-active', '0', 'no', 'tidak', 'false' => false,
            default => 'invalid',
        };
        if ($isActive === 'invalid') {
            $errors[] = "Status \"{$row['status']}\" is not valid (use Active or Inactive).";
        }

        $data = [
            'sku' => $row['sku'],
            'name' => $row['name'] === null ? null : (string) $row['name'],
            'category_id' => $categoryId,
            'description' => ($row['description'] ?? '') === '' ? null : (string) $row['description'],
            'price' => $this->number($row['price']),
            'stock' => $this->number($row['stock']),
        ];

        $validator = Validator::make($data, [
            'sku' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9-]*$/'],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => $categoryName === '' ? ['required'] : [],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ], [
            'sku.regex' => 'The SKU may only contain letters, numbers, and hyphens (e.g. KOP-001).',
        ], [
            'sku' => 'SKU', 'name' => 'product name', 'category_id' => 'category',
        ]);

        foreach ($validator->errors()->all() as $message) {
            $errors[] = $message;
        }

        if (is_bool($isActive)) {
            $data['is_active'] = $isActive;
        }

        return [$data, $errors];
    }

    /** Angka dari sel: 12500 / 12500.5 / "12,500" / "IDR 12,500" → numerik. */
    private function number(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            // Excel menyimpan 3 sebagai 3.0; jangan sampai gagal aturan "integer".
            return floor($value) === $value && abs($value) < PHP_INT_MAX ? (int) $value : round($value, 6);
        }

        if (is_string($value)) {
            $clean = preg_replace('/^(IDR|Rp\.?)\s*/i', '', trim($value)) ?? '';
            $clean = str_replace([',', ' '], '', $clean);

            return $clean === '' ? null : $clean;
        }

        return $value;
    }

    /**
     * @param  list<string>  $errors
     *
     * @throws ValidationException
     */
    private function fail(array $errors, int $totalRows): never
    {
        $messages = [
            'file' => count($errors).' problem(s) found in '.$totalRows.' row(s). Nothing was imported — fix the file and upload it again.',
        ];

        foreach (array_slice($errors, 0, self::MAX_REPORTED_ERRORS) as $i => $message) {
            $messages["rows.{$i}"] = $message;
        }

        if (count($errors) > self::MAX_REPORTED_ERRORS) {
            $messages['rows.'.self::MAX_REPORTED_ERRORS] = '… and '.(count($errors) - self::MAX_REPORTED_ERRORS).' more.';
        }

        throw ValidationException::withMessages($messages);
    }
}
