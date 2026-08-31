<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('number_sequences', function (Blueprint $table) {
            if (!Schema::hasColumn('number_sequences', 'prefix')) {
                $table->string('prefix', 20)->nullable()->after('type');
            }
            if (!Schema::hasColumn('number_sequences', 'pad_length')) {
                $table->unsignedTinyInteger('pad_length')->default(6)->after('prefix');
            }
            if (!Schema::hasColumn('number_sequences', 'start_number')) {
                $table->unsignedInteger('start_number')->default(1)->after('pad_length');
            }
        });

        $defaults = [
            'case' => ['prefix' => 'CASE', 'pad_length' => 6, 'start_number' => 1],
            'technical_proposal' => ['prefix' => 'TC', 'pad_length' => 6, 'start_number' => 1],
            'financial_proposal' => ['prefix' => 'FI', 'pad_length' => 6, 'start_number' => 1],
            'invoice' => ['prefix' => 'INV', 'pad_length' => 6, 'start_number' => 1],
        ];
        foreach ($defaults as $type => $cfg) {
            $exists = DB::table('number_sequences')->where('type', $type)->first();
            if ($exists) {
                DB::table('number_sequences')->where('type', $type)->update([
                    'prefix' => $exists->prefix ?: $cfg['prefix'],
                    'pad_length' => $exists->pad_length ?? $cfg['pad_length'],
                    'start_number' => $exists->start_number ?? $cfg['start_number'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('number_sequences')->insert([
                    'type' => $type,
                    'prefix' => $cfg['prefix'],
                    'pad_length' => $cfg['pad_length'],
                    'start_number' => $cfg['start_number'],
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('number_sequences', function (Blueprint $table) {
            foreach (['prefix', 'pad_length', 'start_number'] as $col) {
                if (Schema::hasColumn('number_sequences', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
