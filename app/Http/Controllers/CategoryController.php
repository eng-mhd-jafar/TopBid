<?php

namespace App\Http\Controllers;

use App\Http\Helpers\ApiResponse;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        // 1. التحقق من أن البيانات القادمة هي مصفوفة وأن محتواها صحيح
        $request->validate([
            '*' => 'required|array', // كل عنصر في المصفوفة الأساسية يجب أن يكون مصفوفة
            '*.name' => 'required|string|max:255',
            '*.slug' => 'required|string|max:255|unique:categories,slug',
        ]);

        // 2. الحصول على البيانات
        $categories = $request->all();

        // 3. إدخال البيانات دفعة واحدة في قاعدة البيانات
        Category::insert($categories);

        return ApiResponse::success('All categories created successfully');
    }
}
