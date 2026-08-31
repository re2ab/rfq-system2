<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('link')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
