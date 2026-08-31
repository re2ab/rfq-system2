<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('contacts', 'phone2')) {
                $table->string('phone2')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('contacts', 'fax')) {
                $table->string('fax')->nullable()->after('mobile');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'phone2')) {
                $table->dropColumn('phone2');
            }
            if (Schema::hasColumn('contacts', 'fax')) {
                $table->dropColumn('fax');
            }
        });
    }
};
