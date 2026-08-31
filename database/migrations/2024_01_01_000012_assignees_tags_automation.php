<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('case_user')) {
            Schema::create('case_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['case_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('task_user')) {
            Schema::create('task_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['task_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('color', 20)->default('#0f766e');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('taggables')) {
            Schema::create('taggables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
                $table->morphs('taggable');
                $table->timestamps();
                $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
            });
        }

        if (!Schema::hasTable('automation_rules')) {
            Schema::create('automation_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->string('trigger'); // status_changed | days_in_status | task_overdue
                $table->json('conditions')->nullable();
                $table->string('action'); // create_task | notify_assignees | notify_role
                $table->json('action_payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('case_user');
    }
};
