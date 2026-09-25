<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

final class AtsContext
{
    /**
     * @param  list<string>  $requiredSkills
     */
    public function __construct(
        public readonly string $text,
        public readonly string $mime,
        public readonly ?int $pages,
        public readonly array $requiredSkills = [],
    ) {}
}
