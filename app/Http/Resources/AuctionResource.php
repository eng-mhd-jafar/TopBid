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
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ],

            'seller' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],

            'image' => [
                'path' => $this->image_path,
                'url' => $this->image_path ? asset('storage/'.$this->image_path) : null,
            ],

            'specs' => $this->specs,

            'prices' => [
                'starting' => (float) $this->starting_price,
                'current' => (float) $this->current_price,
            ],

            'duration_hours' => $this->duration_hours,

            'status' => [
                'is_active' => (bool) $this->is_active,
                // التخزين يستخدم flagged بينما مفردات الـ API تستخدم rejected
                'moderation' => $this->resource->isRejected() ? 'rejected' : $this->moderation_status,
            ],

            'times' => [
                'started_at' => optional($this->started_at)->toIso8601String(),
                'expires_at' => optional($this->expires_at)->toIso8601String(),
            ],
        ];
    }
}

