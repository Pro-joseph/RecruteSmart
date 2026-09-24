<?php

declare(strict_types=1);

namespace App\Services\Applications;

use App\Enums\OfferStatus;
use App\Models\Application;
use App\Models\Offer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ApplicationSubmitter
{
    public function __construct(private readonly CaptchaVerifier $captcha) {}

    public function findOfferOrFail(string $token): Offer
    {
        $offer = Offer::where('public_token', $token)->with('formFields')->first();

        if ($offer === null) {
            throw new NotFoundHttpException('Offre introuvable.');
        }

        return $offer;
    }

    public function acceptingReason(Offer $offer): ?string
    {
        if ($offer->status !== OfferStatus::Published) {
            return $offer->status === OfferStatus::Closed ? 'closee' : 'non_publiee';
        }

        if ($offer->deadline_at !== null && $offer->deadline_at->isPast()) {
            return 'depassee';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data  Validated data.
     * @param  array<string, UploadedFile>  $files  Validated extra files keyed by field key.
     */
    public function submit(Offer $offer, array $data, array $files, UploadedFile $cv, string $ip): Application
    {
        if (($reason = $this->acceptingReason($offer)) !== null) {
            throw new UnprocessableEntityHttpException(match ($reason) {
                'closee' => 'Cette offre est clôturée et n’accepte plus de candidatures.',
                'depassee' => 'La date limite de candidature est dépassée.',
                default => 'Cette offre n’accepte pas de candidatures pour le moment.',
            });
        }

        if (! empty($data['website'] ?? null)) {
            throw new UnprocessableEntityHttpException('Envoi refusé.');
        }

        if (! $this->captcha->verify($data['captcha_token'] ?? null, $ip)) {
            throw new UnprocessableEntityHttpException('Vérification anti-spam échouée, veuillez réessayer.');
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if (Application::where('offer_id', $offer->id)->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Cette adresse email a déjà postulé à cette offre.'],
            ]);
        }

        return DB::transaction(function () use ($offer, $data, $files, $cv, $email): Application {
            $directory = "applications/{$offer->id}";

            $cvPath = $cv->storeAs($directory, Str::uuid().'.'.$cv->extension(), 'private');

            $storedFiles = [];
            foreach ($files as $key => $file) {
                $storedFiles[] = [
                    'key' => $key,
                    'path' => $file->storeAs($directory, Str::uuid().'.'.$file->extension(), 'private'),
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            /** @var Application $application */
            $application = $offer->applications()->create([
                'full_name' => trim((string) ($data['full_name'] ?? '')),
                'email' => $email,
                'phone' => isset($data['phone']) && $data['phone'] !== '' ? trim((string) $data['phone']) : null,
                'answers' => $data['answers'] ?? [],
                'cv_path' => $cvPath,
                'cv_original_name' => $cv->getClientOriginalName(),
                'cv_mime' => $cv->getMimeType(),
                'cv_size' => $cv->getSize(),
                'files' => $storedFiles === [] ? null : $storedFiles,
                'consent_at' => now(),
                'consent_version' => config('recruitment.consent_version', 'v1'),
            ]);

            return $application;
        });
    }
}
