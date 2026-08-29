<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\StoreSingleCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;

class AdminCategoryController extends Controller
{
    public function __construct(protected CategoryService $categoryService)
    {
    }

    public function store(StoreSingleCategoryRequest $request)
    {
        $category = $this->categoryService->createCategory($request->validated());

        return ApiResponse::successWithData(
            (new CategoryResource($category))->resolve(),
            'Category created successfully',
            201
        );
    }

    public function update(UpdateCategoryRequest $request, int $id)
    {
        $category = $this->categoryService->updateCategory($id, $request->validated());

        return ApiResponse::successWithData(
            (new CategoryResource($category))->resolve(),
            'Category updated successfully'
        );
    }

    public function destroy(int $id)
    {
        $this->authorize('delete', Category::class);

        // ValidationException عند وجود مزادات مرتبطة تصعد إلى المعالج الموحّد
        $this->categoryService->deleteCategory($id);

        return ApiResponse::success('Category deleted successfully');
    }
}
