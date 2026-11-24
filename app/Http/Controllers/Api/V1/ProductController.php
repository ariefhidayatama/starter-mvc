<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Product\CreateProductAction;
use App\Actions\Product\DeleteProductAction;
use App\Actions\Product\UpdateProductAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\IndexProductRequest;
use App\Http\Requests\Catalog\SaveProductRequest;
use App\Http\Resources\V1\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\ProductQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * /api/v1/products — semua endpoint wajib token + api.access.
 * GET: produk aktif untuk semua user API (nonaktif hanya products.view);
 * tulis: permission products.create/update/delete.
 */
class ProductController extends Controller
{
    public function index(IndexProductRequest $request, ProductQueryService $query): JsonResponse
    {
        $filters = $request->validated();
        $actor = $this->optionalUser($request);

        $products = ($filters['pagination'] ?? 'page') === 'cursor'
            ? $query->cursor($filters, $actor)
            : $query->paginate($filters, $actor);

        return ApiResponse::paginated($products, ProductResource::class);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        // Produk nonaktif → 404 (bukan 403) untuk user tanpa products.view,
        // agar keberadaannya tidak terungkap.
        abort_unless(Gate::forUser($this->optionalUser($request))->allows('view', $product), 404);

        return ApiResponse::success(ProductResource::make($product->load('category:id,name,slug')));
    }

    public function store(SaveProductRequest $request, CreateProductAction $createProduct): JsonResponse
    {
        /** @var array{category_id: int, sku: string, name: string, description?: string|null, price: numeric-string|int|float, stock: int, is_active?: bool} $data */
        $data = $request->validated();

        return ApiResponse::created(ProductResource::make($createProduct->execute($data)), 'Product created.');
    }

    public function update(SaveProductRequest $request, Product $product, UpdateProductAction $updateProduct): JsonResponse
    {
        /** @var array{category_id?: int, sku?: string, name?: string, description?: string|null, price?: numeric-string|int|float, stock?: int, is_active?: bool} $data */
        $data = $request->validated();

        return ApiResponse::success(ProductResource::make($updateProduct->execute($product, $data)), 'Product updated.');
    }

    public function destroy(Product $product, DeleteProductAction $deleteProduct): Response
    {
        Gate::authorize('delete', $product);

        $deleteProduct->execute($product);

        return ApiResponse::noContent();
    }

    /**
     * Semua endpoint wajib token (auth:sanctum + api.access), jadi user
     * selalu ada. Produk nonaktif hanya terlihat oleh products.view.
     */
    private function optionalUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
