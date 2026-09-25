<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** 7-day signed CV link used when a forward exceeds the attachment limit (EF-1005, spec §7.3). */
class ForwardedCvController extends Controller
{
    public function __invoke(string $application): StreamedResponse
    {
        $model = Application::find((int) $application);

        if ($model === null || $model->cv_path === null) {
            throw new NotFoundHttpException;
        }

        $name = 'CV_'.Str::slug($model->full_name, '_');
        $extension = $model->cv_original_name !== null
            ? pathinfo($model->cv_original_name, PATHINFO_EXTENSION)
            : 'pdf';
        if ($extension !== '') {
            $name .= '.'.$extension;
        }

        // Audit trail (spec §4.2 / ENF-11) — no personal data beyond the id.
        Log::info('forward_cv_downloaded', ['application_id' => $model->id]);
        app(AuditLogger::class)->log(
            null,
            AuditAction::CvDownloaded,
            $model,
            ['via' => 'signed_link'],
            Request::ip(),
        );

        return Storage::disk('private')->download(
            $model->cv_path,
            $name,
        );
    }
}
