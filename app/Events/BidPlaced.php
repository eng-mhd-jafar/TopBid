<?php

namespace App\Events;

use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BidPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Bid $bid)
    {
        // البث يحتاج اسم المزايد، وتحميلها هنا يمنع استعلاماً كسولاً
        // أثناء التسلسل خارج سياق الطلب.
        $this->bid->loadMissing('user');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('auction.' . $this->bid->auction_id)];
    }

    /**
     * تحديد البيانات التي سيتم بثها للمتصفح فقط
     */
    public function broadcastWith(): array
    {
        return [
            'bid_id' => $this->bid->id,
            'auction_id' => $this->bid->auction_id,
            'amount' => (float) $this->bid->amount,
            'bidder' => [
                'id' => $this->bid->user?->id,
                'name' => $this->bid->user?->name,
            ],
            'time' => $this->bid->created_at->format('H:i:s'),
            'created_at' => $this->bid->created_at->toIso8601String(),
        ];
    }

    public function broadcastAs()
    {
        return 'bid.placed';
    }
}
