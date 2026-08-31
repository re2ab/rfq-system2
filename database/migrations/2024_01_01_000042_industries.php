<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('industries')) {
            Schema::create('industries', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('code', 40)->nullable()->unique();
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
            $defaults = [
                ['name' => 'نفت و گاز', 'code' => 'oil_gas', 'sort_order' => 1],
                ['name' => 'پتروشیمی', 'code' => 'petrochemical', 'sort_order' => 2],
                ['name' => 'فولاد', 'code' => 'steel', 'sort_order' => 3],
                ['name' => 'آب و فاضلاب', 'code' => 'water', 'sort_order' => 4],
                ['name' => 'پالایشگاهی', 'code' => 'refinery', 'sort_order' => 5],
                ['name' => 'متفرقه', 'code' => 'other', 'sort_order' => 99],
            ];
            foreach ($defaults as $d) {
                DB::table('industries')->insert(array_merge($d, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        if (Schema::hasTable('organizations') && !Schema::hasColumn('organizations', 'industry_id')) {
            Schema::table('organizations', function (Blueprint $t) {
                $t->unsignedBigInteger('industry_id')->nullable()->after('type')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'industry_id')) {
            Schema::table('organizations', function (Blueprint $t) {
                $t->dropColumn('industry_id');
            });
        }
        Schema::dropIfExists('industries');
    }
};
