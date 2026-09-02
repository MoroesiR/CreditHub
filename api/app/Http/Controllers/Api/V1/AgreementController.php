<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Agreements\SignAgreementRequest;
use App\Http\Resources\LoanAgreementResource;
use App\Models\LoanAgreement;
use App\Models\LoanApplication;
use App\Services\Loans\AgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AgreementController extends Controller
{
    public function __construct(
        private readonly AgreementService $agreements,
    ) {}

    /**
     * Draws the agreement for an approved application, or returns the one
     * already drawn. Safe to call twice.
     */
    public function generate(LoanApplication $application): JsonResponse
    {
        try {
            $agreement = $this->agreements->generate(
                $application,
                request()->user(),
                request()->ip(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_CONFLICT,
            );
        }

        return response()->json([
            'data' => new LoanAgreementResource(
                $agreement->load('loanApplication.client'),
            ),
        ]);
    }

    public function show(LoanApplication $application): JsonResponse
    {
        $agreement = $application->agreement()->with('witnessedBy')->first();

        if ($agreement === null) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => new LoanAgreementResource($agreement->load('loanApplication.client')),
        ]);
    }

    public function sign(SignAgreementRequest $request, LoanApplication $application): JsonResponse
    {
        $agreement = $application->agreement()->first();

        if ($agreement === null) {
            return response()->json(
                ['message' => 'No agreement has been drawn for this application yet.'],
                Response::HTTP_CONFLICT,
            );
        }

        try {
            $signed = $this->agreements->sign(
                agreement: $agreement,
                signedName: $request->string('signed_name')->value(),
                signature: $request->string('signature')->value(),
                photo: $request->filled('photo') ? $request->string('photo')->value() : null,
                witnessedBy: $request->user(),
                ipAddress: $request->ip(),
            );
        } catch (RuntimeException $exception) {
            return response()->json(
                ['message' => $exception->getMessage()],
                Response::HTTP_CONFLICT,
            );
        }

        return response()->json([
            'data' => new LoanAgreementResource($signed->load('loanApplication.client', 'witnessedBy')),
        ]);
    }

    /**
     * Streams the signature or the photograph captured at signing. Both are
     * personal data, so neither is ever a public URL.
     */
    public function image(LoanApplication $application, LoanAgreement $agreement, string $kind): StreamedResponse
    {
        abort_unless($agreement->loan_application_id === $application->id, Response::HTTP_NOT_FOUND);
        abort_unless(in_array($kind, ['signature', 'photo'], strict: true), Response::HTTP_NOT_FOUND);

        $path = $kind === 'signature' ? $agreement->signature_path : $agreement->photo_path;

        abort_if($path === null, Response::HTTP_NOT_FOUND);
        abort_unless(Storage::disk($this->agreements->disk())->exists($path), Response::HTTP_NOT_FOUND);

        return Storage::disk($this->agreements->disk())->response($path);
    }
}
