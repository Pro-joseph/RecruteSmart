<?php

declare(strict_types=1);

namespace App\Services\Analysis;

final class LlmAnalysisResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly ?int $tokensIn = null,
        public readonly ?int $tokensOut = null,
        public readonly ?string $model = null,
        public readonly ?string $provider = null,
    ) {}
}
