<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('custom_field_definitions')) {
            Schema::create('custom_field_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('entity'); // case, contact, organization
                $table->string('key');
                $table->string('label');
                $table->string('field_type')->default('text'); // text, number, date, select
                $table->json('options')->nullable();
                $table->boolean('is_required')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['entity', 'key']);
            });
        }
        if (!Schema::hasTable('custom_field_values')) {
            Schema::create('custom_field_values', function (Blueprint $table) {
                $table->id();
                $table->string('entity');
                $table->unsignedBigInteger('entity_id');
                $table->string('key');
                $table->text('value')->nullable();
                $table->timestamps();
                $table->unique(['entity', 'entity_id', 'key']);
                $table->index(['entity', 'entity_id']);
            });
        }
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 5)->default('fa')->after('avatar');
            }
        });
        Schema::table('document_revisions', function (Blueprint $table) {
            if (!Schema::hasColumn('document_revisions', 'status')) {
                $table->string('status')->default('draft')->after('is_locked'); // draft, pending_approval, approved
            }
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_definitions');
        Schema::dropIfExists('app_settings');
    }
};
