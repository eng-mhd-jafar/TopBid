<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * ShouldQueue ضروري: بدونه يُستدعى فايربيز بشكل متزامن داخل الأمر المجدول،
 * فيتوقف إغلاق دفعة كبيرة على عشرات الطلبات الشبكية المتتالية.
 */
class AuctionStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const STATUS_WON = 'won';

    public const STATUS_SOLD = 'sold';

    public const STATUS_LOST = 'lost';

    public const STATUS_EXPIRED_NO_BIDS = 'expired_no_bids';

    protected $auction;

    protected $status;

    public function __construct($auction, $status)
    {
        $this->auction = $auction;
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast', 'fcm'];
    }

    public function toArray($notifiable)
    {
        return [
            'auction_id' => $this->auction->id,
            'message' => $this->message(),
            'status' => $this->status,
            'final_price' => $this->auction->final_price !== null
                ? (float) $this->auction->final_price
                : null,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'auction_id' => $this->auction->id,
            'message' => $this->message(),
            'status' => $this->status,
        ]);
    }

    public function toFcm($notifiable): FcmMessage
    {
        return (new FcmMessage)
            ->setNotification(new FcmNotification(
                title: $this->title(),
                body: $this->message(),
            ))
            ->setData([
                'auction_id' => (string) $this->auction->id,
                'status' => $this->status,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]);
    }

    private function message(): string
    {
        return match ($this->status) {
            self::STATUS_WON => "مبروك! لقد فزت في مزاد: {$this->auction->title}",
            self::STATUS_SOLD => "تم بيع مزادك: {$this->auction->title}",
            self::STATUS_LOST => "انتهى مزاد: {$this->auction->title} وفاز به مزايد آخر.",
            default => "انتهى وقت المزاد: {$this->auction->title} دون وجود مزايدات.",
        };
    }

    private function title(): string
    {
        return match ($this->status) {
            self::STATUS_WON => 'مبروك، لقد فزت 🏆',
            self::STATUS_SOLD => 'تم بيع مزادك 🎉',
            self::STATUS_LOST => 'انتهى المزاد',
            default => 'انتهى المزاد دون مزايدات',
        };
    }
}
