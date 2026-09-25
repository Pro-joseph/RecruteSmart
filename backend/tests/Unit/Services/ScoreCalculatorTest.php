<?php

declare(strict_types=1);

use App\Models\Offer;
use App\Services\Analysis\ScoreCalculator;

function calculatorOffer(array $attributes = []): Offer
{
    return new Offer(array_merge([
        'required_skills' => ['php', 'laravel'],
        'preferred_skills' => ['docker'],
        'min_experience_years' => 3,
        'education_level' => 'bac+3',
        'languages' => [['name' => 'français', 'level' => 'courant']],
    ], $attributes));
}

function calculatorPayload(array $scores): array
{
    $criteria = [];
    foreach ($scores as $key => $score) {
        $criteria[$key] = [
            'score' => $score,
            'evidence' => 'Preuve.',
            ...($key === 'required_skills' || $key === 'preferred_skills'
                ? ['matched' => [], 'missing' => []]
                : []),
        ];
    }

    return ['criteria' => $criteria];
}

it('computes the spec example: 80.5 rounds to 81', function (): void {
    $offer = calculatorOffer();
    $payload = calculatorPayload([
        'required_skills' => 90,
        'preferred_skills' => 50,
        'experience' => 70,
        'education' => 100,
        'languages' => 80,
    ]);

    $result = (new ScoreCalculator)->calculate($offer, $payload);

    expect($result['match_score'])->toBe(81)
        ->and($result['knockout_flags'])->toBe([])
        ->and($result['match_breakdown'])->toHaveKeys(['required_skills', 'preferred_skills', 'experience', 'education', 'languages']);
});

it('renormalizes weights to criteria present in the offer', function (): void {
    $offer = calculatorOffer(['languages' => [], 'education_level' => null]);
    $payload = calculatorPayload([
        'required_skills' => 90,
        'preferred_skills' => 50,
        'experience' => 70,
    ]);

    $result = (new ScoreCalculator)->calculate($offer, $payload);

    // (35*90 + 10*50 + 30*70) / (35+10+30) = 5750/75 = 76.67
    expect($result['match_score'])->toBe(77)
        ->and($result['match_breakdown'])->toHaveKeys(['required_skills', 'preferred_skills', 'experience']);
});

it('flags unsatisfied knockout criteria below the threshold', function (): void {
    $offer = calculatorOffer(['knockout_criteria' => ['required_skills']]);
    $low = (new ScoreCalculator)->calculate($offer, calculatorPayload([
        'required_skills' => 35, 'preferred_skills' => 50, 'experience' => 70,
        'education' => 100, 'languages' => 80,
    ]));

    expect($low['knockout_flags'])->toBe(['required_skills' => true]);

    $ok = (new ScoreCalculator)->calculate($offer, calculatorPayload([
        'required_skills' => 40, 'preferred_skills' => 50, 'experience' => 70,
        'education' => 100, 'languages' => 80,
    ]));

    expect($ok['knockout_flags'])->toBe([]);
});

it('returns null match_score when the offer declares no criteria (RG-08)', function (): void {
    $offer = calculatorOffer([
        'required_skills' => [],
        'preferred_skills' => [],
        'min_experience_years' => null,
        'education_level' => null,
        'languages' => [],
    ]);

    $result = (new ScoreCalculator)->calculate($offer, calculatorPayload(['experience' => 70]));

    expect($result['match_score'])->toBeNull()
        ->and($result['match_breakdown'])->toBe([]);
});

it('skips criteria the LLM did not return', function (): void {
    $offer = calculatorOffer();
    $payload = calculatorPayload(['required_skills' => 90]);

    $result = (new ScoreCalculator)->calculate($offer, $payload);

    expect($result['match_score'])->toBe(90)
        ->and(array_keys($result['match_breakdown']))->toBe(['required_skills']);
});
