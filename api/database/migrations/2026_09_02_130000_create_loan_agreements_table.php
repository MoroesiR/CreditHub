<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The credit agreement, and the evidence that the client signed it.
 *
 * The terms are copied here at generation rather than read back from the
 * application. What the client agreed to is what was on the page in front of
 * them, and it must stay legible years later even if the application record is
 * corrected afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('agreement_number', 20)->unique();

            // The terms as presented, frozen at generation.
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('term_months');
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('monthly_instalment', 15, 2);
            $table->decimal('total_repayable', 15, 2);

            $table->timestamp('generated_at');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();

            // Two separate pieces of evidence: what the client drew, and that
            // they were the one sitting there when they drew it.
            $table->string('signature_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('signed_name')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('witnessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signed_ip', 45)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_agreements');
    }
};
