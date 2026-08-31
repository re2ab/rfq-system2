<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('custom_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('entity'); // case, task, contact, organization
            $table->json('criteria')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('custom_reports');
    }
};
