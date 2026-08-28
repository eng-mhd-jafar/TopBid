<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BidResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'created_at' => optional($this->created_at)->toIso8601String(),

            'auction' => [
                'id' => $this->auction?->id,
                'title' => $this->auction?->title,
                'current_price' => $this->auction ? (float) $this->auction->current_price : null,
            ],
        ];
    }
}
