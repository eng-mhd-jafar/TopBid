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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            // ربط الجهاز بالمستخدم مع حذف التوكنات تلقائياً إذا حُذف المستخدم
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // التوكن الخاص بفايربيز (نضعه فريداً لتجنب التكرار لنفس الجهاز)
            $table->string('fcm_token')->unique();
            // معلومات إضافية للمساعدة في الإدارة
            $table->string('device_type')->nullable(); // ios, android, web
            $table->string('device_name')->nullable(); // e.g., "iPhone 14"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
