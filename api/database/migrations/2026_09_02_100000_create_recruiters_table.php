<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruiters introduce borrowers and earn commission on the loans that result.
 * They are not staff, so they have no row in `users` and never sign in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruiters', function (Blueprint $table): void {
            $table->id();
            $table->string('recruiter_number', 20)->unique();
            $table->string('first_name');
            $table->string('last_name');
            // Commission is income, so a recruiter must be identifiable for tax.
            $table->string('id_number', 13)->unique();
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('bank_name', 80)->nullable();
            $table->string('bank_account_number', 34)->nullable();
            $table->string('bank_branch_code', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruiters');
    }
};
