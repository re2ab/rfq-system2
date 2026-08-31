<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فاز B/C — امضا و پیش‌نویس
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mail_user_signatures')) {
            Schema::create('mail_user_signatures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('locale', 8)->default('fa');
                $table->string('name')->default('پیش‌فرض');
                $table->longText('body_html')->nullable();
                $table->boolean('is_default')->default(true);
                $table->timestamps();
                $table->unique(['user_id', 'locale', 'name']);
            });
        }

        if (!Schema::hasTable('mail_drafts')) {
            Schema::create('mail_drafts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('mail_account_id')->nullable()->constrained('mail_accounts')->nullOnDelete();
                $table->string('to_address')->nullable();
                $table->text('cc')->nullable();
                $table->text('bcc')->nullable();
                $table->string('reply_to')->nullable();
                $table->string('subject')->nullable();
                $table->longText('body_html')->nullable();
                $table->string('in_reply_to')->nullable();
                $table->text('references_header')->nullable();
                $table->unsignedBigInteger('reply_to_message_id')->nullable();
                $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->json('attachment_meta')->nullable();
                $table->string('mode', 16)->default('new');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_drafts');
        Schema::dropIfExists('mail_user_signatures');
    }
};
