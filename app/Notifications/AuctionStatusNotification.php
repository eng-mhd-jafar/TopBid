<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;

class AuctionStatusNotification extends Notification
{
    use Queueable;

    protected $auction;
    protected $status; // 'won' أو 'expired'

    public function __construct($auction, $status)
    {
        $this->auction = $auction;
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable)
    {
        // تخصيص الرسالة المخزنة في قاعدة البيانات
        $message = ($this->status === 'won')
            ? "مبروك! لقد فزت في مزاد: {$this->auction->title}"
            : "انتهى وقت المزاد: {$this->auction->title} دون وجود مزايدات.";

        return [
            'auction_id' => $this->auction->id,
            'message' => $message,
            'status' => $this->status,
        ];
    }

    public function toBroadcast($notifiable)
    {
        $message = ($this->status === 'won')
            ? "تهانينا! فزت بالمزاد 🎉"
            : "للأسف، انتهى المزاد دون مشترين ⏳";

        return new BroadcastMessage([
            'auction_id' => $this->auction->id,
            'message' => $message,
            'status' => $this->status,
        ]);
    }
}