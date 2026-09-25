<?php

declare(strict_types=1);

use App\Enums\ForwardDelivery;
use App\Enums\ForwardStatus;
use App\Models\Application;
use App\Models\Forward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores forward rows with recipients, delivery mode and candidate snapshots', function (): void {
    $owner = User::factory()->create();
    $application = Application::factory()->create();

    $forward = Forward::factory()->create([
        'user_id' => $owner->id,
        'to_emails' => ['a@example.com', 'b@example.com'],
        'include_analysis' => true,
        'delivery' => ForwardDelivery::Attachments,
        'status' => ForwardStatus::Queued,
    ]);

    $forward->applications()->attach($application->id, [
        'candidate_name_snapshot' => $application->full_name,
    ]);

    $fresh = $forward->fresh();

    expect($fresh->to_emails)->toBe(['a@example.com', 'b@example.com'])
        ->and($fresh->delivery)->toBe(ForwardDelivery::Attachments)
        ->and($fresh->status)->toBe(ForwardStatus::Queued)
        ->and($fresh->include_analysis)->toBeTrue()
        ->and($fresh->applications()->count())->toBe(1)
        ->and($fresh->applications()->first()->pivot->candidate_name_snapshot)
        ->toBe($application->full_name);

    $user = $owner->fresh();
    expect($user->forwards()->count())->toBe(1);
});
