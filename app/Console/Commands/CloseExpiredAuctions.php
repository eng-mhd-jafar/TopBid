<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Notifications\AuctionStatusNotification;
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
        $closed = 0;

        // chunkById لا chunk: الحلقة تعدّل is_active وهو أحد شروط الاستعلام،
        // والترقيم بالمعرّف لا بالإزاحة يمنع تخطّي صفوف بسبب ذلك.
        Auction::awaitingClosure()
            ->with([
                'user',
                'bids' => fn ($query) => $query->orderByDesc('amount')->with('user'),
            ])
            ->chunkById(100, function ($auctions) use (&$closed) {
                foreach ($auctions as $auction) {
                    $this->closeAuction($auction);
                    $closed++;
                }
            });

        $this->info("تم إغلاق {$closed} مزادات بنجاح.");

        return self::SUCCESS;
    }

    private function closeAuction(Auction $auction): void
    {
        $winningBid = $auction->bids->first();

        $auction->forceFill([
            'is_active' => false,
            'closed_at' => now(),
            'winner_id' => $winningBid?->user_id,
            'winning_bid_id' => $winningBid?->id,
            // يبقى null إن لم يُبَع، فلا سعر نهائي لمزاد بلا مزايدات
            'final_price' => $winningBid?->amount,
        ])->save();

        if (! $winningBid) {
            $auction->user->notify(new AuctionStatusNotification($auction, 'expired_no_bids'));

            return;
        }

        $winningBid->user->notify(new AuctionStatusNotification($auction, 'won'));

        // البائع لم يكن يُشعَر ببيع مزاده إطلاقاً
        $auction->user->notify(new AuctionStatusNotification($auction, 'sold'));

        // ولا كان الخاسرون يعلمون بانتهاء المزاد
        $auction->bids
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->reject(fn ($user) => $user->id === $winningBid->user_id)
            ->each(fn ($user) => $user->notify(new AuctionStatusNotification($auction, 'lost')));
    }
}
