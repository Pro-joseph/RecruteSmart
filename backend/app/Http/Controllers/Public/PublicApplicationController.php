<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SubmitApplicationRequest;
use App\Mail\ApplicationReceivedMail;
use App\Services\Applications\ApplicationSubmitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PublicApplicationController extends Controller
{
    public function __construct(private readonly ApplicationSubmitter $submitter) {}

    public function store(SubmitApplicationRequest $request, string $token): JsonResponse
    {
        $offer = $this->submitter->findOfferOrFail($token);
        $data = $request->validated();

        /** @var array<string, UploadedFile> $files */
        $files = array_filter(
            (array) $request->validated('files', []),
            fn ($file) => $file instanceof UploadedFile && $file->getPathname() !== ''
        );
        unset($files['cv']);

        $application = $this->submitter->submit(
            $offer,
            $data,
            $files,
            $request->file('files.cv'),
            $request->ip() ?? '127.0.0.1'
        );

        // Confirmation email must never break the submission (ENF-03).
        try {
            $application->setRelation('offer', $offer);
            Mail::to($application->email)->send(new ApplicationReceivedMail($application));
        } catch (\Throwable $e) {
            Log::warning('Confirmation email failed', ['application_id' => $application->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Candidature envoyée. Vous recevrez un email de confirmation.',
        ], 201);
    }
}
