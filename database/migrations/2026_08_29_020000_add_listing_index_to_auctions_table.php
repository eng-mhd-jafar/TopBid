<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * القائمة العامة ترشّح دائماً على الأعمدة الثلاثة نفسها ثم ترتّب بأحدها.
 * الفهرس المركّب يخدم الترشيح والترتيب معاً، وخصوصاً ending_soon الذي
 * يرتّب بـ expires_at بعد ترشيحه بالعمودين قبله.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->index(
                ['moderation_status', 'is_active', 'expires_at'],
                'auctions_listing_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropIndex('auctions_listing_index');
        });
    }
};
