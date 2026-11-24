<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Category\CreateCategoryAction;
use App\Actions\Category\DeleteCategoryAction;
use App\Actions\Category\UpdateCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\IndexCategoryRequest;
use App\Http\Requests\Catalog\SaveCategoryRequest;
use App\Http\Resources\V1\CategoryResource;
use App\Models\Category;
use App\Services\Catalog\CategoryQueryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * /api/v1/categories — semua endpoint wajib token + api.access.
 * GET: semua user API; tulis: permission categories.create/update/delete.
 */
class CategoryController extends Controller
{
    public function index(IndexCategoryRequest $request, CategoryQueryService $query): JsonResponse
    {
        /** @var array{search?: string|null, sort?: string|null, per_page?: int|string|null} $filters */
        $filters = $request->validated();

        return ApiResponse::paginated($query->paginate($filters), CategoryResource::class);
    }

    public function show(Category $category): JsonResponse
    {
        return ApiResponse::success(CategoryResource::make($category->loadCount('products')));
    }

    public function store(SaveCategoryRequest $request, CreateCategoryAction $createCategory): JsonResponse
    {
        /** @var array{name: string, slug?: string|null, description?: string|null} $data */
        $data = $request->validated();

        return ApiResponse::created(CategoryResource::make($createCategory->execute($data)), 'Category created.');
    }

    public function update(SaveCategoryRequest $request, Category $category, UpdateCategoryAction $updateCategory): JsonResponse
    {
        /** @var array{name: string, slug?: string|null, description?: string|null} $data */
        $data = $request->validated();

        return ApiResponse::success(CategoryResource::make($updateCategory->execute($category, $data)), 'Category updated.');
    }

    public function destroy(Category $category, DeleteCategoryAction $deleteCategory): Response
    {
        Gate::authorize('delete', $category);

        $deleteCategory->execute($category);

        return ApiResponse::noContent();
    }
}
