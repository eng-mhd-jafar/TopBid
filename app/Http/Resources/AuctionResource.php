<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuctionResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,

            'category' => [
                'id' => $this->category_id,
            ],

            'seller' => [
                'id' => $this->user_id,
            ],

            'image' => [
                'path' => $this->image_path,
            ],

            'specs' => $this->specs,

            'prices' => [
                'starting' => (float) $this->starting_price,
                'current' => (float) $this->current_price,
            ],

            'duration_hours' => $this->duration_hours,

            'status' => [
                'is_active' => (bool) $this->is_active,
                'moderation' => $this->moderation_status,
            ],

            'times' => [
                'started_at' => optional($this->started_at)->toIso8601String(),
                'expires_at' => optional($this->expires_at)->toIso8601String(),
            ],
        ];
    }
}

