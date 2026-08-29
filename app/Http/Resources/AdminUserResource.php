<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'avatar' => $this->avatar ? asset('storage/'.$this->avatar) : null,
            'is_admin' => (bool) $this->is_admin,
            'email_verified_at' => optional($this->email_verified_at)->toIso8601String(),
            'auctions_count' => $this->whenCounted('auctions'),
            'bids_count' => $this->whenCounted('bids'),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
