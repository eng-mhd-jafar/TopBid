<?php

namespace App\DTOs;

class AuctionData
{
    public function __construct(
        public string $title,
        public string $description,
        public int $categoryId,
        public int $userId,
        public float $startingPrice,
        public int $duration_hours,
        public ?string $end_at = null, // أضفنا هذا الحقل
        public ?array $specs = null,
        public ?string $imagePath = null,
    ) {
    }
}
