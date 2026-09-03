<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money received back against a disbursed loan.
 *
 * Receipts are recorded, never edited. A repayment captured in error is
 * corrected by capturing a reversal, so the account history stays a record of
 * what happened rather than a picture of what someone last decided it should
 * look like. That is also why there is no soft delete here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_repayments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->restrictOnDelete();

            // Negative on a reversal, which is the only way a mistake is undone.
            $table->decimal('amount', 15, 2);
            $table->date('received_on');
            $table->string('method', 30);
            // The bank or payroll reference, so a receipt here can be matched
            // to the statement line it came from.
            $table->string('reference')->nullable();
            $table->string('note')->nullable();

            $table->foreignId('reverses_id')->nullable()->constrained('loan_repayments')->nullOnDelete();

            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['loan_application_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
