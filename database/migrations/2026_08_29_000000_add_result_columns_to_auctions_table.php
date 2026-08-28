<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نتيجة المزاد كانت تُرسل كإشعار ثم تُنسى، فلا توجد أي طريقة للاستعلام
 * عمّن فاز ولا بكم بيع بعد انتهاء المزاد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            // nullOnDelete وليس cascade: حذف الفائز يجب ألا يمحو تاريخ المزاد
            $table->foreignId('winner_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();

            $table->foreignId('winning_bid_id')->nullable()->after('winner_id')
                ->constrained('bids')->nullOnDelete();

            // مفصولة عن المزايدة لتسهيل التقارير دون ضمّ جدول المزايدات
            $table->decimal('final_price', 15, 2)->nullable()->after('current_price');

            $table->timestamp('closed_at')->nullable()->after('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winner_id');
            $table->dropConstrainedForeignId('winning_bid_id');
            $table->dropColumn(['final_price', 'closed_at']);
        });
    }
};
