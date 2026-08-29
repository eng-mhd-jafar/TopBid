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

    public const STATUS_AUCTION_APPROVED = 'auction_approved';

    public const STATUS_AUCTION_REJECTED = 'auction_rejected';

    protected $auction;

    protected $status;

    protected $reason;

    public function __construct($auction, $status, ?string $reason = null)
    {
        $this->auction = $auction;
        $this->status = $status;
        $this->reason = $reason;
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
            'reason' => $this->reason,
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
            self::STATUS_AUCTION_APPROVED => "تمت الموافقة على مزادك: {$this->auction->title}، وبدأ العد التنازلي الآن.",
            self::STATUS_AUCTION_REJECTED => $this->reason
                ? "تم رفض مزادك: {$this->auction->title}. السبب: {$this->reason}"
                : "تم رفض مزادك: {$this->auction->title}.",
            default => "انتهى وقت المزاد: {$this->auction->title} دون وجود مزايدات.",
        };
    }

    private function title(): string
    {
        return match ($this->status) {
            self::STATUS_WON => 'مبروك، لقد فزت 🏆',
            self::STATUS_SOLD => 'تم بيع مزادك 🎉',
            self::STATUS_LOST => 'انتهى المزاد',
            self::STATUS_AUCTION_APPROVED => 'تمت الموافقة على مزادك ✅',
            self::STATUS_AUCTION_REJECTED => 'تم رفض مزادك',
            default => 'انتهى المزاد دون مزايدات',
        };
    }
}
