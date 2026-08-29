<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetAuctionsRequest extends FormRequest
{
    /** القائمة العامة، متاحة للزائر */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => [
                'sometimes',
                'numeric',
                'min:0',
                // المقارنة تُطبَّق فقط عند إرسال الحد الأدنى معها
                Rule::when($this->has('min_price'), ['gte:min_price']),
            ],
            'sort' => 'sometimes|string|in:ending_soon,newest,price_asc,price_desc',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'max_price.gte' => 'The maximum price must be greater than or equal to the minimum price.',
            'sort.in' => 'Sort must be one of: ending_soon, newest, price_asc, price_desc.',
        ];
    }
}
