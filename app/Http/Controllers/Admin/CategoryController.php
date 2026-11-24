<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Category\CreateCategoryAction;
use App\Actions\Category\DeleteCategoryAction;
use App\Actions\Category\UpdateCategoryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\IndexCategoryRequest;
use App\Http\Requests\Catalog\SaveCategoryRequest;
use App\Http\Resources\V1\CategoryResource;
use App\Models\Category;
use App\Services\Catalog\CategoryQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Categories (Web / Inertia).
 *
 * Bandingkan dengan Api\V1\CategoryController: Form Request, Action,
 * Service & Resource-nya SAMA. Yang berbeda hanya bentuk response.
 */
class CategoryController extends Controller
{
    public function index(IndexCategoryRequest $request, CategoryQueryService $query): Response
    {
        /** @var array{search?: string|null, sort?: string|null, per_page?: int|string|null} $filters */
        $filters = $request->validated();

        return Inertia::render('admin/categories/index', [
            // Resource API dipakai ulang untuk membentuk data tabel.
            'categories' => $query->paginate($filters)
                ->through(fn (Category $c) => CategoryResource::make($c)->resolve($request)),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'sort' => $filters['sort'] ?? 'name',
                'per_page' => (int) ($filters['per_page'] ?? 10),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Category::class);

        return Inertia::render('admin/categories/create');
    }

    public function store(SaveCategoryRequest $request, CreateCategoryAction $createCategory): RedirectResponse
    {
        /** @var array{name: string, slug?: string|null, description?: string|null} $data */
        $data = $request->validated();
        $category = $createCategory->execute($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Category {$category->name} created."]);

        return to_route('admin.categories.index');
    }

    public function edit(Category $category): Response
    {
        Gate::authorize('update', $category);

        return Inertia::render('admin/categories/edit', [
            'category' => CategoryResource::make($category->loadCount('products'))->resolve(),
        ]);
    }

    public function update(SaveCategoryRequest $request, Category $category, UpdateCategoryAction $updateCategory): RedirectResponse
    {
        /** @var array{name: string, slug?: string|null, description?: string|null} $data */
        $data = $request->validated();
        $updateCategory->execute($category, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Category {$category->name} updated."]);

        return to_route('admin.categories.index');
    }

    public function destroy(Category $category, DeleteCategoryAction $deleteCategory): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $deleteCategory->execute($category);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Category {$category->name} deleted."]);

        return to_route('admin.categories.index');
    }
}
