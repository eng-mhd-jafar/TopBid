<?php

namespace App\Notifications;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * يُرسَل لمن كان صاحب أعلى مزايدة حين تتجاوزه مزايدة جديدة.
 *
 * BidService يرسله بعد إغلاق الترانزاكشن ويبتلع أي فشل، فمزايدة نجحت
 * يجب ألا تبدو فاشلة لأن خدمة الدفع الخارجية تعطّلت. تبقى afterCommit
 * حماية إضافية لو استُدعي هذا الإشعار يوماً من داخل ترانزاكشن.
 */
class OutbidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const STATUS = 'outbid';

    public function __construct(protected Auction $auction, protected Bid $newBid)
    {
        // الخاصية معرّفة في Queueable، فتُضبط عبر دالته لا بإعادة تعريفها
        $this->afterCommit();
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
            'status' => self::STATUS,
            'amount' => (float) $this->newBid->amount,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'auction_id' => $this->auction->id,
            'message' => $this->message(),
            'status' => self::STATUS,
            'amount' => (float) $this->newBid->amount,
        ]);
    }

    public function toFcm($notifiable): FcmMessage
    {
        return (new FcmMessage)
            ->setNotification(new FcmNotification(
                title: 'تمت المزايدة عليك',
                body: $this->message(),
            ))
            ->setData([
                'auction_id' => (string) $this->auction->id,
                'status' => self::STATUS,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ]);
    }

    private function message(): string
    {
        $amount = (float) $this->newBid->amount;

        return "تمت المزايدة عليك في مزاد: {$this->auction->title}. السعر الحالي {$amount}.";
    }
}
