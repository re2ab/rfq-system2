<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('custom_field_values', function (Blueprint $table) {
            if (!Schema::hasColumn('custom_field_values', 'show_in_info')) {
                $table->boolean('show_in_info')->default(false)->after('value');
            }
        });
    }
    public function down(): void
    {
        Schema::table('custom_field_values', function (Blueprint $table) {
            if (Schema::hasColumn('custom_field_values', 'show_in_info')) {
                $table->dropColumn('show_in_info');
            }
        });
    }
};
