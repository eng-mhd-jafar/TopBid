<?php

namespace App\Console\Commands;

use App\Models\Auction;
use Illuminate\Console\Command;

class CloseExpiredAuctions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auctions:close';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'إغلاق المزادات التي انتهى وقتها';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. نجيب كل المزادات اللي انتهى وقتها ولسه مفتوحة
        $expiredAuctions = Auction::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->get();

        // 2. منمر على كل مزاد منتهي ومنسكره
        foreach ($expiredAuctions as $auction) {
            $auction->update(['is_active' => false]);

            $winningBid = $auction->bids()->latest()->first();

            if ($winningBid) {
                // حالة وجود فائز: نرسل الإشعار للفائز
                $winner = $winningBid->user;
                $winner->notify(new \App\Notifications\AuctionStatusNotification($auction, 'won'));
            } else {
                // حالة عدم وجود مزايدات: نرسل الإشعار لصاحب المزاد (البائع)
                $seller = $auction->seller;
                $seller->notify(new \App\Notifications\AuctionStatusNotification($auction, 'expired_no_bids'));
            }
        }

        $this->info("تم إغلاق " . count(value: $expiredAuctions) . " مزادات.");
    }
}
