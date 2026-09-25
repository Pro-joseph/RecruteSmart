<?php

declare(strict_types=1);

namespace App\Services\Forwards;

use App\Enums\ApplicationEventType;
use App\Enums\ForwardDelivery;
use App\Enums\ForwardStatus;
use App\Jobs\SendForwardJob;
use App\Models\Application;
use App\Models\Forward;
use App\Models\Offer;
use App\Models\User;
use App\Services\Applications\ApplicationFilter;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ForwardService
{
    /**
     * Creates the forward row and its candidate snapshots (spec §7.1), then
     * queues the actual sending. Returns 202-style, status stays « queued ».
     *
     * @param array{
     *     to_emails: list<string>,
     *     subject: string,
     *     message?: string|null,
     *     include_analysis?: bool,
     *     application_ids?: list<int>,
     *     offer_id?: int,
     *     filters?: array<string, mixed>,
     *     select_all?: bool
     * } $data
     */
    public function create(User $user, array $data): Forward
    {
        $applications = $this->resolveApplications($user, $data);

        if ($applications === []) {
            throw new UnprocessableEntityHttpException('Aucun candidat sélectionné.');
        }

        if (count($applications) > (int) config('recruitment.forward.max_candidates')) {
            throw new UnprocessableEntityHttpException(
                'Trop de candidats : maximum '.config('recruitment.forward.max_candidates').' par transfert.',
            );
        }

        $forward = DB::transaction(function () use ($applications, $data, $user): Forward {
            /** @var Forward $forward */
            $forward = $user->forwards()->create([
                'to_emails' => $data['to_emails'],
                'subject' => $data['subject'],
                'message' => $data['message'] ?? null,
                'include_analysis' => (bool) ($data['include_analysis'] ?? false),
                'delivery' => ForwardDelivery::Attachments,
                'status' => ForwardStatus::Queued,
            ]);

            foreach ($applications as $application) {
                $forward->applications()->attach($application->id, [
                    'candidate_name_snapshot' => $application->full_name,
                ]);

                $application->events()->create([
                    'user_id' => $user->id,
                    'type' => ApplicationEventType::ForwardCreated,
                    'payload' => ['forward_id' => $forward->id],
                ]);
            }

            return $forward;
        });

        SendForwardJob::dispatch($forward->id);

        return $forward;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<Application>
     */
    private function resolveApplications(User $user, array $data): array
    {
        if (! empty($data['application_ids'])) {
            $ids = array_map('intval', (array) $data['application_ids']);

            return Application::query()
                ->whereKey($ids)
                ->forRecruiter($user->id)
                ->get()
                ->all();
        }

        if (! ($data['select_all'] ?? false)) {
            return [];
        }

        /** @var Offer|null $offer */
        $offer = Offer::find($data['offer_id'] ?? 0);

        if ($offer === null || $offer->user_id !== $user->id) {
            throw new UnprocessableEntityHttpException('Offre introuvable.');
        }

        return app(ApplicationFilter::class)
            ->apply($offer->applications()->getQuery(), $data['filters'] ?? [])
            ->with(['analysis', 'skills'])
            ->get()
            ->all();
    }
}
