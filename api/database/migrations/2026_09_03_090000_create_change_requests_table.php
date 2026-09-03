<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests to amend a client or a recruiter.
 *
 * Nobody in origination edits these records directly. An officer who spots a
 * wrong surname or a changed bank account raises a request; an administrator
 * decides it. The reason is that both records decide where money goes - the
 * client is paid the loan and the recruiter is paid commission - so a quiet
 * edit to either is a quiet redirection of funds.
 *
 * The proposed values are held here rather than applied on submission, so the
 * record is only touched when the request is approved, and the request itself
 * remains as the evidence of who asked and who agreed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('reason');
            // field => proposed value. Only whitelisted fields are accepted.
            $table->json('changes');

            $table->string('status', 20)->default('pending')->index();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();

            // What the record actually held before the change went on, so an
            // approved request can be read back years later without guessing.
            $table->json('replaced_values')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
