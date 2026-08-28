<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetAuctionByCategoryRequest extends FormRequest
{
    /**
     * category_id يصل كـ route parameter، و validationData() لا تتضمن باراميترات المسار.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['category_id' => $this->route('category_id')]);
    }

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
