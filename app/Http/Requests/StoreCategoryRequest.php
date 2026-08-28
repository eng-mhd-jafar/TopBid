<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
    }

    public function rules(): array
    {
        return [
            '*' => 'required|array',
            '*.name' => 'required|string|max:255',
            '*.slug' => 'required|string|max:255|unique:categories,slug',
        ];
    }
}
