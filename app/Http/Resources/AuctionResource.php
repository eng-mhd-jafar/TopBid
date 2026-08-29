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

            // تظهر فقط عندما يحمّلها الاستعلام، عبر scopeWithListingData
            'bids_count' => $this->whenCounted('bids'),
            'highest_bidder_id' => $this->whenLoaded(
                'highestBid',
                fn () => $this->highestBid?->user_id
            ),

            'status' => [
                'is_active' => (bool) $this->is_active,
                // التخزين يستخدم flagged بينما مفردات الـ API تستخدم rejected
                'moderation' => $this->resource->isRejected() ? 'rejected' : $this->moderation_status,
                'rejection_reason' => $this->rejection_reason,
            ],

            'times' => [
                'started_at' => optional($this->started_at)->toIso8601String(),
                'expires_at' => optional($this->expires_at)->toIso8601String(),
                'closed_at' => optional($this->closed_at)->toIso8601String(),
            ],

            // تُملأ عند إغلاق المزاد؛ winner_id يبقى null إن انتهى بلا مزايدات
            'result' => [
                'winner_id' => $this->winner_id,
                'final_price' => $this->final_price !== null ? (float) $this->final_price : null,
            ],
        ];
    }
}

