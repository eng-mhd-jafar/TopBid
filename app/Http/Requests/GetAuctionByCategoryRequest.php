<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetAuctionByCategoryRequest extends FormRequest
{
    public function rules()
    {
        return [
            'category_id' => 'required|exists:categories,id',
        ];
    }
    public function messages()
    {
        return [
            'category_id.required' => 'The category is required',
            'category_id.exists' => 'The category does not exist',
        ];
    }
}
