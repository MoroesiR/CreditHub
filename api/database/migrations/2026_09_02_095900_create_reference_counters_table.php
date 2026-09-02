<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sequence counters for human-facing reference numbers.
 *
 * MAX(client_number) + 1 is not safe under concurrency and breaks the moment a
 * row is soft-deleted. A counter row that is locked for the duration of the
 * issuing transaction is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_counters', function (Blueprint $table): void {
            $table->string('key', 40)->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_counters');
    }
};
