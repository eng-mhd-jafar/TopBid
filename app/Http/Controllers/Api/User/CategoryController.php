<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    private $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function store(Request $request)
    {
        $request->validate([
            '*' => 'required|array',
            '*.name' => 'required|string|max:255',
            '*.slug' => 'required|string|max:255|unique:categories,slug',
        ]);
        $this->categoryService->storeCategories($request->all());

        return ApiResponse::success('All categories created successfully');
    }

    public function index()
    {
        $categories = $this->categoryService->getAllCategories();

        return ApiResponse::successWithData(CategoryResource::collection($categories), 'Categories fetched successfully');
    }
}
