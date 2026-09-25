<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApplicationEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationEvent
 */
class ApplicationEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ApplicationEvent $event */
        $event = $this->resource;

        return [
            'id' => $event->id,
            'type' => $event->type->value,
            'payload' => $event->payload ?? [],
            'user' => $event->user === null ? null : [
                'id' => $event->user->id,
                'name' => $event->user->name,
                'email' => $event->user->email,
            ],
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }
}
