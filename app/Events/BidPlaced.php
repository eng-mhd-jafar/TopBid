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
        //
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
            'auction_id' => $this->bid->auction_id,
            'amount' => $this->bid->amount,
            'time' => $this->bid->created_at->format('H:i:s'),
        ];
    }

    public function broadcastAs()
    {
        return 'bid.placed';
    }
}
