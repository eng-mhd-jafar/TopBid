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
        // 1. منجيب كل المزادات اللي لساتها شغال (is_active = 1) 
        // وبنفس الوقت وقت النهاية تبعها صار بالماضي (expires_at <= now)
        $expiredAuctions = Auction::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->get();

        // 2. منمر على كل مزاد منتهي ومنسكره
        foreach ($expiredAuctions as $auction) {
            $auction->update(['is_active' => false]);

            // 3. (اختياري حالياً) ممكن نطلق Event هون مشان يخبر السوكيت
            // event(new AuctionClosed($auction));
        }

        $this->info("تم إغلاق " . count($expiredAuctions) . " مزادات.");
    }
}
