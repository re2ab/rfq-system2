<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('backup_jobs')) {
            Schema::create('backup_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('filename');
                $table->string('path')->nullable();
                $table->string('type')->default('manual');
                $table->string('scope')->nullable();
                $table->boolean('encrypted')->default(true);
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->string('status')->default('done');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
            return;
        }
        Schema::table('backup_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('backup_jobs', 'scope')) {
                $table->string('scope')->nullable()->after('type');
            }
        });
    }
    public function down(): void
    {
        //
    }
};
