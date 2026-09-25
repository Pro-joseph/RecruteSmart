<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

final class AtsCheckResult
{
    public function __construct(
        public readonly string $code,
        public readonly int $earned,
        public readonly int $max,
        public readonly string $message,
        public readonly string $advice,
    ) {}

    public function passed(): bool
    {
        return $this->earned >= $this->max;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'points' => $this->earned,
            'max' => $this->max,
            'passed' => $this->passed(),
            'message' => $this->message,
            'advice' => $this->advice,
        ];
    }
}
