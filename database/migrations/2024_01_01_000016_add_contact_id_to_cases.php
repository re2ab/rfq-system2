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
            if (!Schema::hasColumn('cases', 'contact_id')) {
                $table->foreignId('contact_id')->nullable()->after('customer_organization_id')
                    ->constrained('contacts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cases') && Schema::hasColumn('cases', 'contact_id')) {
            Schema::table('cases', function (Blueprint $table) {
                $table->dropConstrainedForeignId('contact_id');
            });
        }
    }
};
