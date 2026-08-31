<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_mail_accounts')) {
            Schema::create('user_mail_accounts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->unique();
                $t->string('email')->nullable(); // corporate mailbox e.g. user1@mycompany.com
                $t->string('display_name')->nullable();
                // SMTP
                $t->string('smtp_host')->nullable();
                $t->unsignedSmallInteger('smtp_port')->default(587);
                $t->string('smtp_encryption', 16)->default('tls'); // tls|ssl|none
                $t->string('smtp_username')->nullable();
                $t->text('smtp_password')->nullable(); // encrypted via Crypt when saving
                // IMAP
                $t->string('imap_host')->nullable();
                $t->unsignedSmallInteger('imap_port')->default(993);
                $t->string('imap_encryption', 16)->default('ssl');
                $t->string('imap_username')->nullable();
                $t->text('imap_password')->nullable();
                // POP3 optional
                $t->string('pop3_host')->nullable();
                $t->unsignedSmallInteger('pop3_port')->default(995);
                $t->string('pop3_encryption', 16)->default('ssl');
                $t->string('pop3_username')->nullable();
                $t->text('pop3_password')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamp('last_synced_at')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('modules')) {
            DB::table('modules')->updateOrInsert(
                ['key' => 'user_mailbox'],
                [
                    'name' => 'صندوق ایمیل شرکتی هر کاربر',
                    'is_core' => false,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('user_mail_accounts');
    }
};
