<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Helpers\ApiResponse;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            '*' => 'required|array',
            '*.name' => 'required|string|max:255',
            '*.slug' => 'required|string|max:255|unique:categories,slug',
        ]);

        $categories = $request->all();

        Category::insert($categories);

        return ApiResponse::success('All categories created successfully');
    }
}

