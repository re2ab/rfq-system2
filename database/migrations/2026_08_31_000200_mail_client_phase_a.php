<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فاز A — پایه‌ی کلاینت ایمیل یکپارچه:
 * چند اکانت، تخصیص دسترسی توسط ادمین، فولدرها، ذخیرهٔ محلی پیام‌ها و پیوست‌ها.
 * جداول قدیمی user_mail_accounts / emails دست‌نخورده می‌مانند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mail_accounts')) {
            Schema::create('mail_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // برچسب داخلی مثلاً «مرکزی info» یا «علی — sales»
                $table->string('email')->index();
                $table->string('display_name')->nullable();
                $table->boolean('is_shared')->default(false); // اکانت مرکزی/اشتراکی
                $table->boolean('is_active')->default(true);

                $table->string('smtp_host')->nullable();
                $table->unsignedSmallInteger('smtp_port')->default(587);
                $table->string('smtp_encryption', 16)->default('tls'); // tls|ssl|none
                $table->string('smtp_username')->nullable();
                $table->text('smtp_password')->nullable(); // Crypt

                $table->string('imap_host')->nullable();
                $table->unsignedSmallInteger('imap_port')->default(993);
                $table->string('imap_encryption', 16)->default('ssl');
                $table->string('imap_username')->nullable();
                $table->text('imap_password')->nullable();
                $table->string('imap_sent_folder')->nullable();

                $table->timestamp('last_synced_at')->nullable();
                $table->text('last_sync_error')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mail_account_user')) {
            Schema::create('mail_account_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('can_read')->default(true);
                $table->boolean('can_send')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->unique(['mail_account_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('mail_folders')) {
            Schema::create('mail_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
                $table->string('name'); // نمایشی
                $table->string('remote_path'); // مسیر IMAP مثلاً INBOX یا INBOX.Sent
                $table->string('role', 32)->default('custom'); // inbox|sent|drafts|trash|spam|archive|custom
                $table->string('delimiter', 8)->default('.');
                $table->unsignedBigInteger('uidvalidity')->nullable();
                $table->unsignedInteger('message_count')->default(0);
                $table->unsignedInteger('unseen_count')->default(0);
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();
                $table->unique(['mail_account_id', 'remote_path']);
            });
        }

        if (!Schema::hasTable('mail_messages')) {
            Schema::create('mail_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
                $table->foreignId('mail_folder_id')->constrained('mail_folders')->cascadeOnDelete();
                $table->unsignedBigInteger('uid')->index(); // UID پایدار در فولدر
                $table->string('message_id')->nullable()->index();
                $table->string('in_reply_to')->nullable()->index();
                $table->text('references_header')->nullable();
                $table->string('thread_key', 191)->nullable()->index();

                $table->string('from_address')->nullable()->index();
                $table->string('from_name')->nullable();
                $table->json('to_json')->nullable();
                $table->json('cc_json')->nullable();
                $table->json('bcc_json')->nullable();
                $table->string('reply_to')->nullable();

                $table->string('subject')->nullable();
                $table->longText('body_text')->nullable();
                $table->longText('body_html')->nullable();
                $table->timestamp('date_sent')->nullable()->index();

                $table->boolean('is_seen')->default(false);
                $table->boolean('is_flagged')->default(false);
                $table->boolean('is_answered')->default(false);
                $table->boolean('is_draft')->default(false);
                $table->boolean('has_attachments')->default(false);
                $table->unsignedInteger('size')->default(0);

                // ادغام دامنه RFQ (فاز D کامل‌تر می‌شود؛ ستون‌ها از الان آماده)
                $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->unsignedBigInteger('organization_id')->nullable()->index();

                $table->text('raw_headers')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['mail_folder_id', 'uid']);
            });
        }

        if (!Schema::hasTable('mail_message_attachments')) {
            Schema::create('mail_message_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_message_id')->constrained('mail_messages')->cascadeOnDelete();
                $table->string('part_number', 64)->nullable();
                $table->string('filename')->nullable();
                $table->string('mime', 128)->nullable();
                $table->unsignedInteger('size')->default(0);
                $table->string('content_id')->nullable();
                $table->string('storage_path')->nullable(); // اگر روی دیسک ذخیره شود
                $table->boolean('is_inline')->default(false);
                $table->timestamps();
            });
        }

        // ماژول
        if (Schema::hasTable('modules')) {
            DB::table('modules')->updateOrInsert(
                ['key' => 'unified_mail'],
                [
                    'name' => 'کلاینت ایمیل یکپارچه',
                    'name_en' => 'Unified Mail Client',
                    'is_core' => false,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // مهاجرت داده از user_mail_accounts (اگر وجود دارد)
        if (Schema::hasTable('user_mail_accounts') && Schema::hasTable('mail_accounts')) {
            $rows = DB::table('user_mail_accounts')->get();
            foreach ($rows as $row) {
                $exists = DB::table('mail_accounts')
                    ->where('email', $row->email ?: ($row->smtp_username ?: 'user-'.$row->user_id.'@local'))
                    ->exists();
                if ($exists) {
                    continue;
                }
                $accountId = DB::table('mail_accounts')->insertGetId([
                    'name' => $row->display_name ?: ($row->email ?: 'Mailbox #'.$row->user_id),
                    'email' => $row->email ?: ($row->smtp_username ?: 'user-'.$row->user_id.'@local'),
                    'display_name' => $row->display_name,
                    'is_shared' => false,
                    'is_active' => (bool) $row->is_active,
                    'smtp_host' => $row->smtp_host,
                    'smtp_port' => $row->smtp_port ?? 587,
                    'smtp_encryption' => $row->smtp_encryption ?? 'tls',
                    'smtp_username' => $row->smtp_username,
                    'smtp_password' => $row->smtp_password,
                    'imap_host' => $row->imap_host,
                    'imap_port' => $row->imap_port ?? 993,
                    'imap_encryption' => $row->imap_encryption ?? 'ssl',
                    'imap_username' => $row->imap_username,
                    'imap_password' => $row->imap_password,
                    'last_synced_at' => $row->last_synced_at,
                    'created_by' => $row->user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('mail_account_user')->insert([
                    'mail_account_id' => $accountId,
                    'user_id' => $row->user_id,
                    'can_read' => true,
                    'can_send' => true,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_message_attachments');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_folders');
        Schema::dropIfExists('mail_account_user');
        Schema::dropIfExists('mail_accounts');
        if (Schema::hasTable('modules')) {
            DB::table('modules')->where('key', 'unified_mail')->delete();
        }
    }
};
