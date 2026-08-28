<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Illuminate\Notifications\DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = (array) $this->data;

        return [
            'id' => $this->id,
            'type' => class_basename($this->type),
            'message' => $data['message'] ?? null,
            'status' => $data['status'] ?? null,
            'auction_id' => $data['auction_id'] ?? null,
            'is_read' => $this->read_at !== null,
            'read_at' => optional($this->read_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
