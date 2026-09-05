<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fees on a credit agreement.
 *
 * The columns default to zero so that loans written before fees existed keep
 * the terms they were actually sold on. A change to what the lender charges
 * prices new agreements; it never reaches back and repriced ones already on
 * the book, and a migration that backfilled these would do exactly that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table): void {
            // Once-off, capitalised, so it is financed and carries interest.
            $table->decimal('initiation_fee', 15, 2)->default(0)->after('amount');
            // The advance plus the initiation fee: what interest is charged on.
            $table->decimal('amount_financed', 15, 2)->default(0)->after('initiation_fee');
            // Flat monthly charge that earns nothing and reduces nothing, so it
            // sits outside the amortisation.
            $table->decimal('monthly_service_fee', 15, 2)->default(0)->after('interest_rate');
        });

        Schema::table('loan_agreements', function (Blueprint $table): void {
            $table->decimal('initiation_fee', 15, 2)->default(0)->after('amount');
            $table->decimal('amount_financed', 15, 2)->default(0)->after('initiation_fee');
            $table->decimal('monthly_service_fee', 15, 2)->default(0)->after('interest_rate');
        });

        Schema::table('loan_repayments', function (Blueprint $table): void {
            // How the receipt was split. The National Credit Act prescribes the
            // order, so the split is a fact about the payment and is stored
            // with it rather than recomputed later from a running balance.
            $table->decimal('fee_portion', 15, 2)->default(0)->after('amount');
            $table->decimal('interest_portion', 15, 2)->default(0)->after('fee_portion');
            $table->decimal('capital_portion', 15, 2)->default(0)->after('interest_portion');
        });
    }

    public function down(): void
    {
        Schema::table('loan_repayments', function (Blueprint $table): void {
            $table->dropColumn(['fee_portion', 'interest_portion', 'capital_portion']);
        });

        Schema::table('loan_agreements', function (Blueprint $table): void {
            $table->dropColumn(['initiation_fee', 'amount_financed', 'monthly_service_fee']);
        });

        Schema::table('loan_applications', function (Blueprint $table): void {
            $table->dropColumn(['initiation_fee', 'amount_financed', 'monthly_service_fee']);
        });
    }
};
