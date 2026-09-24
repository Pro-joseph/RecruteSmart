<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SubmitApplicationRequest;
use App\Services\Applications\ApplicationSubmitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

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

        $this->submitter->submit(
            $offer,
            $data,
            $files,
            $request->file('files.cv'),
            $request->ip() ?? '127.0.0.1'
        );

        return response()->json([
            'message' => 'Candidature envoyée. Vous recevrez un email de confirmation.',
        ], 201);
    }
}
