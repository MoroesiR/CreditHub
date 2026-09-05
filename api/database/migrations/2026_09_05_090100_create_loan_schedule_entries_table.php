<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The repayment schedule, written once when the money goes out.
 *
 * Arrears were previously worked out by counting the months since payout and
 * multiplying by the instalment. That gives the right answer only while every
 * loan is a straight equal instalment and nothing is ever restructured, and it
 * cannot say what a given payment was for.
 *
 * A stored schedule fixes both. Each row is one instalment split into what it
 * is made of, so a receipt can be applied against fees, then interest, then
 * capital, in the order the National Credit Act prescribes, and a statement
 * can show the client where their money went.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_schedule_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('instalment_number');
            $table->date('due_on');

            // What this instalment is made of. They sum to total_due.
            $table->decimal('service_fee_due', 15, 2)->default(0);
            $table->decimal('interest_due', 15, 2)->default(0);
            $table->decimal('capital_due', 15, 2)->default(0);
            $table->decimal('total_due', 15, 2);

            // The balance still owed after this instalment is paid, which is
            // what a settlement quote is worked out from.
            $table->decimal('closing_balance', 15, 2);

            $table->timestamps();

            // Named explicitly: the generated names run past the 64 character
            // limit MariaDB enforces on identifiers.
            $table->unique(['loan_application_id', 'instalment_number'], 'schedule_loan_instalment_unique');
            $table->index(['loan_application_id', 'due_on'], 'schedule_loan_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_schedule_entries');
    }
};
