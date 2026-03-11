<?php

namespace App\Observers;

use App\Models\Auction;

class AuctionObserver
{
    /**
     * Handle the Auction "created" event.
     */
    public function created(Auction $auction): void
    {
        //
    }

    /**
     * Handle the Auction "updated" event.
     */
    public function updated(Auction $auction)
    {
        // إذا تغيرت الحالة إلى approved وكانت سابقاً pending
        if ($auction->wasChanged('moderation_status') && $auction->moderation_status === 'approved') {

            // منع التكرار: نحدث البيانات فقط إذا لم يكن قد بدأ فعلياً
            if (!$auction->started_at) {
                $auction->is_active = true;
                $auction->started_at = now();
                $auction->expires_at = now()->addHours($auction->duration_hours);
                $auction->save();
            }
        }
    }

    /**
     * Handle the Auction "deleted" event.
     */
    public function deleted(Auction $auction): void
    {
        //
    }

    /**
     * Handle the Auction "restored" event.
     */
    public function restored(Auction $auction): void
    {
        //
    }

    /**
     * Handle the Auction "force deleted" event.
     */
    public function forceDeleted(Auction $auction): void
    {
        //
    }
}
