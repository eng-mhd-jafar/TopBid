<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    // app/Http/Requests/StoreDeviceTokenRequest.php
    public function rules(): array
    {
        return [
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|string|in:ios,android,web',
            'device_name' => 'nullable|string|max:255',
        ];
    }
}
