<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Organizations
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('type')->nullable(); // customer, supplier, both
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Contacts
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('position')->nullable(); // سمت سازمانی
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('avatar')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['first_name', 'last_name']);
            $table->index('email');
        });

        // Contact Confidential Notes
        Schema::create('contact_confidential_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });

        // Cases (پرونده‌ها)
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->foreignId('customer_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('assigned_expert_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('current_status')->default('received'); // enum values
            $table->string('previous_status')->nullable(); // for resume from stopped
            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->string('currency')->default('EUR'); // EUR, IRR
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->string('incoterm')->nullable(); // CPT, CFR, DDP, ...
            $table->text('won_reason')->nullable();
            $table->text('lost_reason')->nullable();
            $table->text('stopped_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('current_status');
            $table->index('assigned_expert_id');
            $table->index('created_at');
        });

        // Case Status History
        Schema::create('case_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->boolean('is_override')->default(false);
            $table->timestamps();
        });

        // Case Suppliers
        Schema::create('case_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        // Case Activities (comments + phone call reports)
        Schema::create('case_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('comment'); // comment, phone_call_report
            $table->text('body');
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('call_datetime')->nullable();
            $table->string('call_direction')->nullable(); // incoming, outgoing
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('call_result')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('case_activities')->nullOnDelete(); // nested
            $table->timestamps();
            $table->softDeletes();

            // $table->fullText('body'); // optional: enable if InnoDB fulltext available
        });

        // Activity Likes & Reactions
        Schema::create('activity_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('like'); // like, emoji code
            $table->string('emoji')->nullable();
            $table->timestamps();

            $table->unique(['case_activity_id', 'user_id', 'type']);
        });

        // Mentions
        Schema::create('activity_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Tasks
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('task_type')->nullable(); // follow_up, call, proposal, ...
            $table->string('priority')->default('medium');
            $table->string('status')->default('open'); // open, in_progress, done, cancelled, overdue
            $table->timestamp('due_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_team')->default(false); // همگانی / تیمی
            $table->boolean('requires_approval')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_to', 'status']);
            $table->index('due_at');
        });

        // Task Checklist
        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Documents & Revisions
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // technical_proposal, financial_proposal, invoice
            $table->string('document_number')->unique();
            $table->string('title')->nullable();
            $table->string('currency')->default('EUR');
            $table->decimal('exchange_rate', 18, 6)->nullable();
            $table->string('incoterm')->nullable();
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->longText('content')->nullable(); // for full-text search
            $table->string('file_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            // $table->fullText('content');
        });

        // Deliveries
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->string('delivery_number')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        // Receivables & Payments
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete(); // invoice
            $table->string('currency')->default('EUR');
            $table->decimal('amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('status')->default('PENDING'); // PENDING, PARTIALLY_PAID, PAID, OVERDUE
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('payment_date');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Follow-ups
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('remind_at');
            $table->string('priority')->default('medium');
            $table->text('note')->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestamps();
        });

        // Emails
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction'); // inbound, outbound
            $table->string('from_address');
            $table->string('to_address');
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->string('message_id')->nullable();
            $table->boolean('is_linked')->default(false);
            $table->timestamps();
        });

        // Number Sequences (for safe sequential numbering)
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique(); // case, technical_proposal, financial_proposal, invoice
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        // Modules (for modular enable/disable)
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_core')->default(false);
            $table->timestamps();
        });

        // System Settings
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Document & Email Templates
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // technical_proposal, financial_proposal, invoice, email
            $table->string('name');
            $table->string('code')->nullable();
            $table->longText('header')->nullable();
            $table->longText('body')->nullable();
            $table->longText('footer')->nullable();
            $table->string('account_type')->nullable(); // internal, external (for email)
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });

        // Attachments
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Audit Logs (simple version)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        $tables = [
            'audit_logs', 'attachments', 'templates', 'system_settings', 'modules',
            'number_sequences', 'emails', 'follow_ups', 'payments', 'receivables',
            'deliveries', 'document_revisions', 'documents', 'task_checklist_items',
            'tasks', 'activity_mentions', 'activity_reactions', 'case_activities',
            'case_suppliers', 'case_status_histories', 'cases',
            'contact_confidential_notes', 'contacts', 'organizations'
        ];
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
