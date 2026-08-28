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
        $expiredAuctions = Auction::awaitingClosure()
            ->with([
                'user',
                'bids' => function ($query) {
                    $query->orderByDesc('amount')->with('user');
                }
            ])
            ->get();

        foreach ($expiredAuctions as $auction) {
            $auction->update(['is_active' => false]);

            $winningBid = $auction->bids->first();

            if ($winningBid) {
                $winner = $winningBid->user;
                $winner->notify(new \App\Notifications\AuctionStatusNotification($auction, 'won'));
            } else {
                $seller = $auction->user;
                $seller->notify(new \App\Notifications\AuctionStatusNotification($auction, 'expired_no_bids'));
            }
        }
        $this->info("تم إغلاق " . $expiredAuctions->count() . " مزادات بنجاح.");
    }
}
