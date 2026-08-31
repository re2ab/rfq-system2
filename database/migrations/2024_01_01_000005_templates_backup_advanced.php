<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('templates')) {
            Schema::create('templates', function (Blueprint $table) {
                $table->id();
                $table->string('type');
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('header')->nullable();
                $table->longText('body')->nullable();
                $table->text('footer')->nullable();
                $table->string('account_type')->nullable();
                $table->boolean('is_default')->default(false);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
            });
        } else {
            Schema::table('templates', function (Blueprint $table) {
                if (!Schema::hasColumn('templates', 'version')) {
                    $table->unsignedInteger('version')->default(1);
                }
            });
        }

        if (!Schema::hasTable('template_versions')) {
            Schema::create('template_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id');
                $table->unsignedInteger('version_number');
                $table->string('name')->nullable();
                $table->text('header')->nullable();
                $table->longText('body')->nullable();
                $table->text('footer')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->text('change_note')->nullable();
                $table->timestamps();
                $table->index(['template_id', 'version_number']);
            });
        }

        if (!Schema::hasTable('backup_jobs')) {
            Schema::create('backup_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('filename');
                $table->string('path');
                $table->string('type')->default('manual'); // manual, scheduled
                $table->boolean('encrypted')->default(false);
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->string('status')->default('done'); // done, failed
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        // default schedule settings via app_settings if table exists
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_jobs');
        Schema::dropIfExists('template_versions');
    }
};
