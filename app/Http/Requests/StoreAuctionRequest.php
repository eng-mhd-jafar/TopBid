<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuctionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'starting_price' => 'required|numeric|min:0',
            'duration_hours' => 'required|integer|min:1|max:168', // حد أقصى أسبوع
            'specs' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ];
    }
    public function messages(): array
    {
        return [
            'title.required' => 'The title is required',
            'title.string' => 'The title must be a string',
            'title.max' => 'The title must be less than 255 characters',

            'description.required' => 'The description is required',
            'description.string' => 'The description must be a string',

            'category_id.required' => 'The category is required',
            'category_id.exists' => 'The category does not exist',

            'starting_price.required' => 'The starting price is required',
            'starting_price.numeric' => 'The starting price must be a number',
            'starting_price.min' => 'The starting price must be greater than 0',

            'duration_hours.required' => 'The duration hours is required',
            'duration_hours.integer' => 'The duration hours must be an integer',
            'duration_hours.min' => 'The duration hours must be greater than 0',
            'duration_hours.max' => 'The duration hours must be less than 168',

            'specs.array' => 'The specs must be an array',

            'image.image' => 'The image must be an image',
            'image.mimes' => 'The image must be a jpeg, png, or jpg',
            'image.max' => 'The image must be less than 2048KB',
        ];
    }
}
