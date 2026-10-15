<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 20)->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('priority', 20);
            $table->string('status', 30);
            $table->foreignId('category_id')->constrained();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('sla_breached_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'assigned_agent_id']);
            $table->index(['status', 'sla_due_at']);
            $table->index(['priority', 'status']);
            $table->index(['customer_id', 'created_at']);
            $table->index('created_at');
            $table->fullText(['subject', 'description']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
