<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetAdminAuctionsRequest extends FormRequest
{
    /**
     * الصلاحية مضبوطة عبر middleware('admin') على المسار.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|string|in:active,expired,pending,approved,rejected',
            'search' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
