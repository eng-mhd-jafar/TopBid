<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectAuctionRequest extends FormRequest
{
    /**
     * الصلاحية مضبوطة عبر middleware('admin') على المسار، والتحقق الدقيق
     * من الصلاحية على هذا المزاد تحديداً يتم داخل المتحكم.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
