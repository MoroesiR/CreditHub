<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrowers. A client is registered once and keeps the same client number for
 * every loan they subsequently take.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('client_number', 20)->unique();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('id_number', 13)->unique();
            // Both are read out of the ID number rather than captured, but are
            // stored so the book can be reported on without re-parsing every
            // ID on every query.
            $table->date('date_of_birth');
            $table->string('gender', 10);
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('province', 80)->nullable();
            $table->string('postal_code', 10)->nullable();
            // The lender operates in one country. Stored rather than assumed
            // so the column is already there if that ever stops being true.
            $table->string('country', 80)->default('South Africa');

            $table->string('employer_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('employment_status', 40)->default('permanent');

            $table->string('bank_name', 80)->nullable();
            $table->string('bank_account_number', 34)->nullable();
            $table->string('bank_branch_code', 10)->nullable();

            // Null when the client walked in rather than being introduced.
            $table->foreignId('recruiter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index('recruiter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
