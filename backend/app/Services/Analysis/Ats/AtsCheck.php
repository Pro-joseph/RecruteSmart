<?php

declare(strict_types=1);

namespace App\Services\Analysis\Ats;

interface AtsCheck
{
    public function code(): string;

    public function evaluate(AtsContext $context): AtsCheckResult;
}
