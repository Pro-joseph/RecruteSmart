<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** EF-906: recruiter-customisable confirmation / invitation / refusal copy. */
class EmailTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $custom = $request->user()
            ->emailTemplates()
            ->get()
            ->keyBy('key');

        /** @var array<string, array{subject: string, body: string, customised: bool}> $data */
        $data = [];
        foreach (EmailTemplate::KEYS as $key) {
            $defaults = EmailTemplate::defaults()[$key];
            $customRow = $custom->get($key);

            $data[$key] = [
                'subject' => $customRow->subject ?? $defaults['subject'],
                'body' => $customRow->body ?? $defaults['body'],
                'customised' => $customRow !== null,
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        abort_unless(in_array($key, EmailTemplate::KEYS, true), 404);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $request->user()->emailTemplates()->updateOrCreate(
            ['key' => $key],
            $validated,
        );

        return response()->json([
            'message' => "Modèle d'email enregistré.",
            'data' => ['key' => $key, ...$validated, 'customised' => true],
        ]);
    }

    public function reset(Request $request, string $key): JsonResponse
    {
        abort_unless(in_array($key, EmailTemplate::KEYS, true), 404);

        $request->user()->emailTemplates()->where('key', $key)->delete();

        return response()->json(['message' => "Modèle d'email réinitialisé."]);
    }
}
