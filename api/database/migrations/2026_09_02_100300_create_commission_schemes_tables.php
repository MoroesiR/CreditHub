<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission pricing.
 *
 * A scheme is never edited once loans have been priced under it - a change
 * means a new version, and every commission row records the version it was
 * priced under. Without that, restating a historical payout is guesswork.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_schemes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->boolean('is_active')->default(false);
            $table->date('effective_from');
            $table->timestamps();

            $table->unique(['name', 'version']);
        });

        Schema::create('commission_scheme_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commission_scheme_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_amount', 15, 2);
            // Null is the open-ended top tier.
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->decimal('rate_percent', 5, 2);
            // Null means the rate is uncapped within the tier.
            $table->decimal('cap_amount', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['commission_scheme_id', 'min_amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_scheme_tiers');
        Schema::dropIfExists('commission_schemes');
    }
};
