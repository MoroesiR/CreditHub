<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('application_number', 20)->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            // Carried from the client at capture time: the recruiter who earns
            // on this loan must not change if the client is later reassigned.
            $table->foreignId('recruiter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('affordability_assessment_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('term_months');
            $table->decimal('interest_rate', 5, 2);
            $table->string('purpose')->nullable();

            // The quote as it stood when the application was made. Stored, not
            // recomputed on read: the instalment a client was shown and agreed
            // to must not move when the rate or the formula changes.
            $table->decimal('monthly_instalment', 15, 2);
            $table->decimal('total_repayable', 15, 2);
            // Copied from the assessment in force at capture, so the decision
            // can be defended against the figure it was actually made on.
            $table->decimal('disposable_income_at_capture', 15, 2)->nullable();

            $table->string('status', 30)->default('draft')->index();

            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decline_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
