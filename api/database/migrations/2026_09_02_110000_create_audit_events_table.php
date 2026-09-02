<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail.
 *
 * Who did what, to which record, and when. A lender has to be able to answer
 * that about any file years later - which recruiter a client was attached to
 * on a given date, and who attached them.
 *
 * Events are append-only: there is a created_at and no updated_at, no soft
 * delete, and nothing in the application updates a row once written. An audit
 * trail that can be edited is not one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();

            // Null only if the actor's account is later deleted; the event
            // itself must survive that.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name');

            $table->string('action', 60)->index();
            $table->morphs('auditable');

            // Written at the time in plain language, so the trail stays
            // readable without re-deriving it from ids years later.
            $table->string('summary');
            $table->json('metadata')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
