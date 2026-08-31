<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_lines')) {
            Schema::create('document_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('description');
                $table->string('unit')->nullable()->default('عدد');
                $table->decimal('quantity', 18, 3)->default(1);
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->decimal('line_total', 18, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('email_attachments')) {
            Schema::create('email_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('email_message_id')->constrained('emails')->cascadeOnDelete();
                $table->string('file_name');
                $table->string('file_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->unsignedBigInteger('source_attachment_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
        Schema::dropIfExists('document_lines');
    }
};
