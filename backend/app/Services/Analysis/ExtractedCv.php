<?php

declare(strict_types=1);

namespace App\Services\Analysis;

final class ExtractedCv
{
    public function __construct(
        public readonly string $text,
        public readonly string $mime,
        public readonly ?int $pages,
    ) {}
}
