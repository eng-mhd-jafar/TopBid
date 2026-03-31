<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // عادةً يرجع التوكن مع الاستجابة بعد التسجيل
            'token' => $this->when(isset($this->token), $this->token),
        ];
    }
}
