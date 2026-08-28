<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'address' => $this->address,
            'city' => $this->city,
            'bio' => $this->bio,
            'is_admin' => (bool) $this->is_admin,
            // قيمة منطقية لا نصاً مترجماً؛ الترجمة مسؤولية الواجهة
            'has_active_activity' => $this->resource->hasActiveActivity(),
        ];
    }
}
