<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
