<?php

declare(strict_types=1);

use App\Models\Offer;
use App\Models\User;
use App\Services\Offers\FormFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(FormFieldService::class);
    $this->offer = Offer::factory()->for(User::factory()->create())->create();
});

it('exposes the 15-key predefined catalog', function (): void {
    $catalog = $this->service->catalog();

    expect($catalog)->toHaveCount(15)
        ->and($catalog['cv']['locked'])->toBeTrue()
        ->and($catalog['photo']['sensitive'])->toBeTrue()
        ->and($catalog['age']['sensitive'])->toBeTrue();
});

it('rejects sync without the locked trio', function (): void {
    $this->service->sync($this->offer, [
        ['key' => 'full_name', 'label' => 'Nom', 'type' => 'text', 'is_required' => true],
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true],
    ]);
})->throws(ValidationException::class);

it('syncs flags from the catalog, forcing locked required', function (): void {
    $this->service->sync($this->offer, [
        ['key' => 'full_name', 'label' => 'Nom', 'type' => 'text', 'is_required' => true],
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true],
        ['key' => 'cv', 'label' => 'CV', 'type' => 'file', 'is_required' => true],
        ['key' => 'photo', 'label' => 'Photo', 'type' => 'image'],
        ['key' => 'phone', 'label' => 'Tél', 'type' => 'phone', 'is_required' => true],
    ]);

    $fields = $this->offer->fresh()?->formFields->keyBy('key') ?? collect();

    expect($fields['photo']['is_sensitive'])->toBeTrue()
        ->and($fields['phone']['is_required'])->toBeTrue()
        ->and($fields['phone']['is_locked'])->toBeFalse()
        ->and($fields['cv']['is_locked'])->toBeTrue();
});
