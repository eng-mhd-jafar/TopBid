<?php

namespace App\Providers;

use App\Core\Domain\Interfaces\JwtAuthRepositoryInterface;
use App\Models\Auction;
use App\Models\User;
use App\Observers\AuctionObserver;
use App\Repositories\JwtAuthRepository;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(JwtAuthRepositoryInterface::class, function ($app) {
            return new JwtAuthRepository($app->make(User::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Auction::observe(AuctionObserver::class);
    }
}
