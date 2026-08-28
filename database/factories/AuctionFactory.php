<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Auction>
 */
class AuctionFactory extends Factory
{
    protected $model = Auction::class;

    /**
     * الحالة الافتراضية: مزاد قيد المراجعة تماماً كما ينشئه AuctionRepository::create.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startingPrice = fake()->randomFloat(2, 10, 1000);

        return [
            'category_id' => Category::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'image_path' => null,
            'specs' => ['color' => 'black', 'condition' => 'new'],
            'starting_price' => $startingPrice,
            'current_price' => $startingPrice,
            'duration_hours' => 24,
            'is_active' => false,
            'moderation_status' => 'pending',
            'started_at' => null,
            'expires_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'moderation_status' => 'pending',
            'is_active' => false,
            'started_at' => null,
            'expires_at' => null,
        ]);
    }

    /**
     * مزاد معتمد ويعمل الآن.
     *
     * التوقيت يُضبط هنا صراحةً لأن AuctionObserver يستمع لحدث updated فقط،
     * فالمزاد المُنشأ مباشرةً بحالة approved لا يمر على الـ Observer إطلاقاً.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => 'approved',
            'is_active' => true,
            'started_at' => now(),
            'expires_at' => now()->addHours($attributes['duration_hours'] ?? 24),
        ]);
    }

    public function flagged(): static
    {
        return $this->state(fn () => [
            'moderation_status' => 'flagged',
            'is_active' => false,
        ]);
    }

    /**
     * مزاد معتمد لكن انتهى وقته (لا يزال is_active حتى يمر عليه أمر الإغلاق).
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'moderation_status' => 'approved',
            'is_active' => true,
            'started_at' => now()->subHours(($attributes['duration_hours'] ?? 24) + 1),
            'expires_at' => now()->subHour(),
        ]);
    }
}
