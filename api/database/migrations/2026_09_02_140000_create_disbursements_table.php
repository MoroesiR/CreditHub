<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The payout queue.
 *
 * A row appears here the moment an agreement is signed, and it is the only
 * place money leaves the business. Verification and payment are recorded as
 * two separate acts with two separate timestamps, because "who checked it" and
 * "who released it" are different questions an auditor will ask separately.
 *
 * The client's bank details are copied here at payout rather than read from
 * the client record: where the money actually went must not change if the
 * client updates their account afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->unique()->constrained()->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            $table->string('status', 30)->default('pending')->index();

            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_reference')->nullable();

            // Where the money went, as it stood at the moment of payment.
            $table->string('paid_to_bank_name', 80)->nullable();
            $table->string('paid_to_account_number', 34)->nullable();
            $table->string('paid_to_branch_code', 10)->nullable();

            $table->string('hold_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
