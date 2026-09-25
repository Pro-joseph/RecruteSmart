<?php

declare(strict_types=1);

use App\Enums\AtsVerdict;
use App\Services\Analysis\Ats\AtsContext;
use App\Services\Analysis\Ats\AtsScorer;

function atsGoodText(): string
{
    return <<<'TXT'
        Jean Dupont — Développeur backend
        jean.dupont@exemple.fr — 06 12 34 56 78

        Expérience
        Développeur backend chez ACME, 03/2021 - 06/2024. APIs PHP et Laravel,
        files d'attente Redis, déploiements Docker et tests automatisés au quotidien.

        Formation
        Licence informatique, 09/2018 - 06/2021, université de Paris.

        Compétences
        PHP, Laravel, PostgreSQL, Docker, Git
        TXT;
}

function atsCheck(array $results, string $code): array
{
    foreach ($results as $result) {
        if ($result['code'] === $code) {
            return $result;
        }
    }

    throw new RuntimeException("Check {$code} not found.");
}

it('scores a fully compliant CV at 100', function (): void {
    $context = new AtsContext(
        text: atsGoodText(),
        mime: 'application/pdf',
        pages: 1,
        requiredSkills: ['php', 'laravel', 'postgresql', 'docker'],
    );

    $score = (new AtsScorer)->score($context);

    expect($score['score'])->toBe(100)
        ->and($score['verdict'])->toBe(AtsVerdict::Compliant);
});

it('fails ATS-01 on insufficient text', function (): void {
    $context = new AtsContext(text: 'Court', mime: 'application/pdf', pages: 1);

    $score = (new AtsScorer)->score($context);

    expect($score['score'])->toBe(35)
        ->and($score['verdict'])->toBe(AtsVerdict::NonCompliant)
        ->and(atsCheck($score['checks'], 'ATS-01')['passed'])->toBeFalse();
});

it('awards partial points for detected sections only', function (): void {
    $context = new AtsContext(
        text: "Expérience\n".str_repeat('Projet interesting avec beaucoup de détails. ', 10),
        mime: 'application/pdf',
        pages: 1,
    );

    $ats02 = atsCheck((new AtsScorer)->score($context)['checks'], 'ATS-02');

    expect($ats02['points'])->toBe(8);
});

it('awards 5 points for a single recognizable period', function (): void {
    $context = new AtsContext(
        text: "Expérience\nSeul poste depuis 03/2021 avec des responsabilités variées.",
        mime: 'application/pdf',
        pages: 1,
    );

    $ats05 = atsCheck((new AtsScorer)->score($context)['checks'], 'ATS-05');

    expect($ats05['points'])->toBe(5);
});

it('scores ATS-06 as the fraction of required skills found', function (): void {
    $context = new AtsContext(
        text: atsGoodText(),
        mime: 'application/pdf',
        pages: 1,
        requiredSkills: ['php', 'laravel', 'spring', 'kubernetes'],
    );

    $ats06 = atsCheck((new AtsScorer)->score($context)['checks'], 'ATS-06');

    expect($ats06['points'])->toBe(13);
});

it('gives full ATS-06 points when the offer has no required skills', function (): void {
    $context = new AtsContext(
        text: atsGoodText(),
        mime: 'application/pdf',
        pages: 1,
        requiredSkills: [],
    );

    $ats06 = atsCheck((new AtsScorer)->score($context)['checks'], 'ATS-06');

    expect($ats06['points'])->toBe(25);
});

it('matches skill aliases without substrings false positives', function (): void {
    $context = new AtsContext(
        text: atsGoodText()."\nStack : js, postgres",
        mime: 'application/pdf',
        pages: 1,
        requiredSkills: ['javascript', 'postgresql', 'go'],
    );

    $ats06 = atsCheck((new AtsScorer)->score($context)['checks'], 'ATS-06');

    // js → javascript, postgres → postgresql matched; "go" not present as a word.
    expect($ats06['points'])->toBe(17);
});

it('derives verdicts from the spec thresholds', function (): void {
    expect(AtsScorer::verdictFor(100))->toBe(AtsVerdict::Compliant)
        ->and(AtsScorer::verdictFor(75))->toBe(AtsVerdict::Compliant)
        ->and(AtsScorer::verdictFor(74))->toBe(AtsVerdict::Improvable)
        ->and(AtsScorer::verdictFor(50))->toBe(AtsVerdict::Improvable)
        ->and(AtsScorer::verdictFor(49))->toBe(AtsVerdict::NonCompliant)
        ->and(AtsScorer::verdictFor(0))->toBe(AtsVerdict::NonCompliant);

    // Spec §4.2 enum wire values.
    expect(AtsVerdict::Compliant->value)->toBe('compliant')
        ->and(AtsVerdict::Improvable->value)->toBe('improvable')
        ->and(AtsVerdict::NonCompliant->value)->toBe('non_compliant');
});
