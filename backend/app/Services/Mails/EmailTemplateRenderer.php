<?php

declare(strict_types=1);

namespace App\Services\Mails;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Collection;

/** Resolves the copy actually sent: a custom row (EF-906) or the built-in default. */
class EmailTemplateRenderer
{
    /**
     * @param  array<string, string|int|float|null>  $vars  placeholders such as {candidate_name}
     * @return array{subject: string, body: string}
     */
    public function render(User $owner, string $key, array $vars): array
    {
        $custom = EmailTemplate::query()
            ->where('user_id', $owner->id)
            ->where('key', $key)
            ->first();

        $defaults = EmailTemplate::defaults()[$key] ?? ['subject' => '', 'body' => ''];

        return [
            'subject' => $this->fill($custom->subject ?? $defaults['subject'], $vars),
            'body' => $this->fill($custom->body ?? $defaults['body'], $vars),
        ];
    }

    /**
     * @param  array<string, string|int|float|null>  $vars
     */
    private function fill(string $template, array $vars): string
    {
        $search = Collection::make($vars)
            ->map(static fn (string|int|float|null $value, string $key): string => '{'.$key.'}')
            ->all();
        $replace = Collection::make($vars)
            ->map(static fn (string|int|float|null $value): string => (string) $value)
            ->all();

        return str_replace($search, $replace, $template);
    }
}
