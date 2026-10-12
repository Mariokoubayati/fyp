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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 8, 2);
            $table->string('purpose', 200)->nullable();
            $table->date('due_date');
            $table->string('status', 20)->default('requested');
            $table->timestamp('lender_handover_at')->nullable();
            $table->timestamp('borrower_handover_at')->nullable();
            $table->timestamp('lender_repayment_at')->nullable();
            $table->timestamp('borrower_repayment_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('repaid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index(['borrower_id', 'status']);
            $table->index(['lender_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
