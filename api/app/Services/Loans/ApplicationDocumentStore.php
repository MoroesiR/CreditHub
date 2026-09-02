<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Enums\ApplicationDocumentType;
use App\Models\ApplicationDocument;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes supporting documents to private storage.
 *
 * Two rules hold here. The file is stored under a generated name - the name
 * the browser supplied is attacker-controlled and must never decide a path on
 * disk. And it goes to the private disk, never `public/`: a payslip and a bank
 * statement reachable by guessing a URL is a data breach.
 */
final class ApplicationDocumentStore
{
    private const DISK = 'local';

    public const MAX_KILOBYTES = 5120;

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public function store(
        LoanApplication $application,
        ApplicationDocumentType $type,
        UploadedFile $file,
        User $uploadedBy,
    ): ApplicationDocument {
        $name = Str::uuid()->toString().'.'.$file->extension();

        $path = $file->storeAs(
            "applications/{$application->id}",
            $name,
            ['disk' => self::DISK],
        );

        return ApplicationDocument::updateOrCreate(
            ['loan_application_id' => $application->id, 'type' => $type],
            [
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize() ?: 0,
                'uploaded_by' => $uploadedBy->id,
            ],
        );
    }

    public function disk(): string
    {
        return self::DISK;
    }

    public function exists(ApplicationDocument $document): bool
    {
        return Storage::disk(self::DISK)->exists($document->path);
    }
}
