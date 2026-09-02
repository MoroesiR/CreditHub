<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table): void {
            $table->id();
            // One commission per loan: the unique key is the guard against a
            // recalculation paying a recruiter twice.
            $table->foreignId('loan_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('recruiter_id')->constrained()->restrictOnDelete();
            $table->foreignId('commission_scheme_id')->constrained()->restrictOnDelete();

            // The inputs are stored beside the result so a payout can be
            // explained without re-reading the scheme it was priced under.
            $table->decimal('loan_amount', 15, 2);
            $table->decimal('rate_applied', 5, 2);
            $table->decimal('amount', 15, 2);

            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('calculated_at');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['recruiter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
