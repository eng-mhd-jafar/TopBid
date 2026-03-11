<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('title');
            $table->text('description');
            $table->string('image_path')->nullable();
            $table->json('specs')->nullable(); // المواصفات التقنية

            // تفاصيل المزايدة
            $table->decimal('starting_price', 15, 2);
            $table->decimal('current_price', 15, 2)->nullable(); // سيتم تحديثه مع كل مزايدة
            $table->integer('duration_hours')->default(24); // مدة المزاد المطلوبة

            // التحكم والذكاء الاصطناعي
            $table->boolean('is_active')->default(false);
            $table->enum('moderation_status', ['pending', 'approved', 'flagged'])->default('pending');

            // التوقيت (يتم ملؤهم عند التفعيل)
            $table->timestamp('started_at')->nullable()->index(); // وقت بدء المزاد
            $table->timestamp('expires_at')->nullable()->index(); // وقت انتهاء المزاد
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
