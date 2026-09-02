<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The supporting documents a loan file is assessed on: identity, bank
 * statement, payslip.
 *
 * Only the metadata lives here. The file itself is written to private storage
 * under a generated name - never the client's own filename, which is attacker
 * controlled and would otherwise decide a path on disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();

            $table->string('type', 40);
            // What the client called it, kept only to name the download.
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');

            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // One document of each kind per application: re-uploading replaces
            // rather than quietly accumulating two payslips that disagree.
            $table->unique(['loan_application_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};
