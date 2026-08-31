<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pipeline_transitions')) {
            Schema::create('pipeline_transitions', function (Blueprint $table) {
                $table->id();
                $table->string('from_key');
                $table->string('to_key');
                $table->boolean('is_allowed')->default(true);
                $table->string('condition_code')->nullable(); // null | receivables_paid | proposal_amount | lost_reason
                $table->timestamps();
                $table->unique(['from_key', 'to_key']);
            });
        }

        $defaults = [
            ['received', 'waiting_info', null],
            ['received', 'stopped', null],
            ['received', 'lost', 'lost_reason'],
            ['waiting_info', 'waiting_offer', null],
            ['waiting_info', 'stopped', null],
            ['waiting_info', 'lost', 'lost_reason'],
            ['waiting_offer', 'waiting_technical', null],
            ['waiting_offer', 'stopped', null],
            ['waiting_offer', 'lost', 'lost_reason'],
            ['waiting_technical', 'technical_sent', null],
            ['waiting_technical', 'stopped', null],
            ['waiting_technical', 'lost', 'lost_reason'],
            ['technical_sent', 'waiting_financial', null],
            ['technical_sent', 'stopped', null],
            ['technical_sent', 'lost', 'lost_reason'],
            ['waiting_financial', 'financial_sent', 'proposal_amount'],
            ['waiting_financial', 'stopped', null],
            ['waiting_financial', 'lost', 'lost_reason'],
            ['financial_sent', 'won', null],
            ['financial_sent', 'lost', 'lost_reason'],
            ['financial_sent', 'stopped', null],
            ['won', 'purchasing', null],
            ['won', 'stopped', null],
            ['purchasing', 'receivables', null],
            ['purchasing', 'stopped', null],
            ['receivables', 'closed', 'receivables_paid'],
            ['receivables', 'stopped', null],
        ];

        foreach ($defaults as [$from, $to, $cond]) {
            if (!DB::table('pipeline_transitions')->where('from_key', $from)->where('to_key', $to)->exists()) {
                DB::table('pipeline_transitions')->insert([
                    'from_key' => $from,
                    'to_key' => $to,
                    'is_allowed' => true,
                    'condition_code' => $cond,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_transitions');
    }
};
