<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affordability is assessed at registration and re-assessed over time, so it
 * is a history against the client rather than columns on the client row. The
 * assessment current when an application is decided is the one that must be
 * defensible later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affordability_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->decimal('gross_monthly_income', 15, 2);
            $table->decimal('net_monthly_income', 15, 2);
            $table->decimal('monthly_living_expenses', 15, 2);
            $table->decimal('monthly_debt_repayments', 15, 2);
            // Stored rather than derived on read: the figure that supported a
            // decision must not change when the arithmetic changes.
            $table->decimal('disposable_income', 15, 2);

            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assessed_at');
            $table->timestamps();

            $table->index(['client_id', 'assessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affordability_assessments');
    }
};
