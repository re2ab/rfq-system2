<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tags') && !Schema::hasColumn('tags', 'entity')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->string('entity', 32)->default('case')->after('color')->index();
            });
            // تگ‌های قبلی را به «پرونده» نسبت بده تا چیزی از دست نرود؛ مدیر می‌تواند ویرایش کند
            DB::table('tags')->whereNull('entity')->orWhere('entity', '')->update(['entity' => 'case']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'entity')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropColumn('entity');
            });
        }
    }
};
