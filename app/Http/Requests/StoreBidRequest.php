<?php

namespace App\Http\Requests;

use App\Models\Auction;
use Illuminate\Foundation\Http\FormRequest;

class StoreBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        $auction = Auction::find($this->input('auction_id'));

        return $auction !== null && $this->user()?->can('bid', $auction) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'auction_id' => 'required|exists:auctions,id',
            'amount' => 'required|numeric|min:0.01',
        ];
    }
}
