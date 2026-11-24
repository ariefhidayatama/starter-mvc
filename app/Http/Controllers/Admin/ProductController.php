<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Product\BulkDeleteProductAction;
use App\Actions\Product\CreateProductAction;
use App\Actions\Product\DeleteProductAction;
use App\Actions\Product\UpdateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\BulkDeleteProductRequest;
use App\Http\Requests\Catalog\ExportProductRequest;
use App\Http\Requests\Catalog\ImportProductRequest;
use App\Http\Requests\Catalog\IndexProductRequest;
use App\Http\Requests\Catalog\SaveProductRequest;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CategoryQueryService;
use App\Services\Catalog\ProductImporter;
use App\Services\Catalog\ProductPdfBuilder;
use App\Services\Catalog\ProductQueryService;
use App\Services\Catalog\ProductSpreadsheetExporter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Products (Web / Inertia).
 */
class ProductController extends Controller
{
    public function index(IndexProductRequest $request, ProductQueryService $query, CategoryQueryService $categories): Response
    {
        $filters = $request->validated();
        /** @var User $user */
        $user = $request->user();
        /** @var array<string, mixed> $filter */
        $filter = $filters['filter'] ?? [];

        return Inertia::render('admin/products/index', [
            'products' => $query->paginate($filters, $user)
                ->through(fn (Product $p) => ProductResource::make($p)->resolve($request)),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'sort' => $filters['sort'] ?? '-created_at',
                'per_page' => (int) ($filters['per_page'] ?? 10),
                'filter' => [
                    'category_id' => (string) ($filter['category_id'] ?? ''),
                    'status' => (string) ($filter['status'] ?? ''),
                    'price_min' => (string) ($filter['price_min'] ?? ''),
                    'price_max' => (string) ($filter['price_max'] ?? ''),
                    'stock_min' => (string) ($filter['stock_min'] ?? ''),
                    'stock_max' => (string) ($filter['stock_max'] ?? ''),
                ],
            ],
            // Daftar kategori hanya berubah jarang; dipakai filter dropdown.
            'categoryOptions' => $categories->options(),
        ]);
    }

    public function create(CategoryQueryService $categories): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('admin/products/create', [
            'categoryOptions' => $categories->options(),
        ]);
    }

    public function store(SaveProductRequest $request, CreateProductAction $createProduct): RedirectResponse
    {
        /** @var array{category_id: int, sku: string, name: string, description?: string|null, price: numeric-string|int|float, stock: int, is_active?: bool} $data */
        $data = $request->validated();
        $product = $createProduct->execute($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Product {$product->name} created."]);

        return to_route('admin.products.index');
    }

    public function edit(Request $request, Product $product, CategoryQueryService $categories): Response
    {
        Gate::authorize('update', $product);

        return Inertia::render('admin/products/edit', [
            'product' => ProductResource::make($product->load('category:id,name,slug'))->resolve($request),
            'categoryOptions' => $categories->options(),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product, UpdateProductAction $updateProduct): RedirectResponse
    {
        /** @var array{category_id?: int, sku?: string, name?: string, description?: string|null, price?: numeric-string|int|float, stock?: int, is_active?: bool} $data */
        $data = $request->validated();
        $updateProduct->execute($product, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Product {$product->name} updated."]);

        return to_route('admin.products.index');
    }

    public function destroy(Product $product, DeleteProductAction $deleteProduct): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $deleteProduct->execute($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Product {$product->name} deleted."]);

        return to_route('admin.products.index');
    }

    /**
     * PDF daftar produk yang dicentang, dibuka inline di tab baru
     * (bisa dicetak atau diunduh dari viewer browser).
     */
    public function pdf(ExportProductRequest $request, ProductPdfBuilder $builder): HttpResponse
    {
        $generatedAt = now();

        return $builder->build($this->selectedProducts($request), $request->user()?->name, $generatedAt)
            ->stream('products-'.$generatedAt->format('Ymd-His').'.pdf');
    }

    /**
     * Upload produk dari Excel / CSV. SKU baru → dibuat, SKU lama → diperbarui.
     * Jika ada baris yang salah, tidak ada yang disimpan (error per baris).
     */
    public function importExcel(ImportProductRequest $request, ProductImporter $importer): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('file');

        ['created' => $created, 'updated' => $updated] = $importer->import($file, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Import finished: {$created} created, {$updated} updated.",
        ]);

        return to_route('admin.products.index');
    }

    /** Template Excel untuk upload produk. */
    public function importTemplate(ProductImporter $importer): StreamedResponse
    {
        return $importer->template();
    }

    /** Excel (.xlsx) produk yang dicentang, langsung diunduh. */
    public function excel(ExportProductRequest $request, ProductSpreadsheetExporter $exporter): StreamedResponse
    {
        return $exporter->xlsx($this->selectedProducts($request), $request->user()?->name, now());
    }

    /** CSV produk yang dicentang, langsung diunduh. */
    public function csv(ExportProductRequest $request, ProductSpreadsheetExporter $exporter): StreamedResponse
    {
        return $exporter->csv($this->selectedProducts($request), now());
    }

    /**
     * Produk yang dicentang (dari ?ids[]=<ulid>), urut nama.
     *
     * @return Collection<int, Product>
     */
    private function selectedProducts(ExportProductRequest $request): Collection
    {
        /** @var list<string> $ids */
        $ids = $request->validated('ids');

        return Product::query()
            ->with('category:id,name')
            ->whereIn('ulid', $ids)
            ->orderBy('name')
            ->get();
    }

    public function bulkDestroy(BulkDeleteProductRequest $request, BulkDeleteProductAction $bulkDelete): RedirectResponse
    {
        /** @var list<string> $ids */
        $ids = $request->validated('ids');
        $count = $bulkDelete->execute($ids);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count === 1 ? '1 product deleted.' : "{$count} products deleted.",
        ]);

        return to_route('admin.products.index');
    }
}
