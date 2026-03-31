<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // محمي مسبقاً بالميدل وير
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $this->user()->id,
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $this->user()->id,
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048', // تحدد حجم الصورة 2MB
        ];
    }
}