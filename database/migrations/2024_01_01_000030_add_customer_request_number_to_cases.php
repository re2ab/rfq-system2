<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cases')) {
            return;
        }
        Schema::table('cases', function (Blueprint $table) {
            if (!Schema::hasColumn('cases', 'customer_request_number')) {
                $table->string('customer_request_number', 100)->nullable()->after('contact_id');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cases') && Schema::hasColumn('cases', 'customer_request_number')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropColumn('customer_request_number');
            });
        }
    }
};
