<?php

declare(strict_types=1);

use App\Services\Analysis\SkillNormalizer;

it('normalizes, aliases and dedupes skills', function (): void {
    $skills = (new SkillNormalizer)->normalizeSkills(['JS', 'Postgres', 'javascript', 'C++', 'Ci/CD', '']);

    expect(array_column($skills, 'slug'))->toBe(['javascript', 'postgresql', 'cplusplus', 'cicd'])
        ->and($skills[0]['name'])->toBe('javascript');
});

it('keeps dotted aliases like node.js', function (): void {
    $skills = (new SkillNormalizer)->normalizeSkills(['Node.js', 'nodejs']);

    expect(array_column($skills, 'slug'))->toBe(['nodejs']);
});

it('normalizes cities to accent-free lowercase', function (): void {
    $normalizer = new SkillNormalizer;

    expect($normalizer->normalizeCity(' Fès '))->toBe('fes')
        ->and($normalizer->normalizeCity('CASABLANCA'))->toBe('casablanca')
        ->and($normalizer->normalizeCity('Ouarzazate   Sud'))->toBe('ouarzazate sud')
        ->and($normalizer->normalizeCity(null))->toBeNull()
        ->and($normalizer->normalizeCity('  '))->toBeNull();
});
