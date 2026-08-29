<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قبل هذا العمود لم تكن هناك طريقة لإبلاغ البائع بسبب رفض مزاده.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('moderation_status');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
