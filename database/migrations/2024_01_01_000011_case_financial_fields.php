<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (!Schema::hasColumn('cases', 'proposal_amount')) {
                $table->decimal('proposal_amount', 18, 2)->nullable()->after('incoterm');
            }
            if (!Schema::hasColumn('cases', 'vat_percent')) {
                $table->decimal('vat_percent', 5, 2)->nullable()->after('proposal_amount');
            }
            if (!Schema::hasColumn('cases', 'proposal_gross')) {
                $table->decimal('proposal_gross', 18, 2)->nullable()->after('vat_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            foreach (['proposal_amount', 'vat_percent', 'proposal_gross'] as $c) {
                if (Schema::hasColumn('cases', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
