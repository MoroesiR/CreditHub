<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The proof attached to a change request.
 *
 * A request to change a surname is worth nothing on its own: the officer is
 * repeating what somebody told them over a telephone. The ID copy is what an
 * administrator actually decides on, and a bank statement is the only evidence
 * that an account belongs to the person being paid.
 *
 * Held against the request rather than written straight onto the client, so
 * the document that was produced in support of a change stays attached to the
 * change even after the record has moved on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_request_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('change_request_id')->constrained()->cascadeOnDelete();

            $table->string('type', 40);
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');

            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // One of each kind per request: a second bank statement would leave
            // an administrator deciding which of two to believe.
            $table->unique(['change_request_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_documents');
    }
};
