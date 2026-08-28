<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;

class CategoryController extends Controller
{
    private $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function store(StoreCategoryRequest $request)
    {
        $this->categoryService->storeCategories($request->validated());

        return ApiResponse::success('All categories created successfully');
    }

    public function index()
    {
        $categories = $this->categoryService->getAllCategories();

        return ApiResponse::successWithData(CategoryResource::collection($categories), 'Categories fetched successfully');
    }
}
