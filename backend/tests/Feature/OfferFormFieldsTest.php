<?php

declare(strict_types=1);

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->offer = Offer::factory()->for($this->user)->create();
});

function lockedFields(): array
{
    return [
        ['key' => 'full_name', 'label' => 'Nom', 'type' => 'text', 'is_required' => true],
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'is_required' => true],
        ['key' => 'cv', 'label' => 'CV', 'type' => 'file', 'is_required' => true],
    ];
}

it('exposes the predefined catalog', function (): void {
    $this->getJson('/api/v1/form-field-catalog')
        ->assertOk()
        ->assertJsonCount(15, 'data');
});

it('syncs form configuration from the catalog', function (): void {
    $fields = [...lockedFields(), ['key' => 'phone', 'label' => 'Tél', 'type' => 'phone', 'is_required' => true]];

    $this->putJson("/api/v1/offers/{$this->offer->id}/form-fields", ['fields' => $fields])
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.3.key', 'phone');

    $this->getJson("/api/v1/offers/{$this->offer->id}/form-fields")
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

it('rejects missing locked fields and client-set flags', function (): void {
    $this->putJson("/api/v1/offers/{$this->offer->id}/form-fields", [
        'fields' => [['key' => 'phone', 'label' => 'Tél', 'type' => 'phone']],
    ])->assertUnprocessable();

    $fields = [...lockedFields()];
    $fields[0]['is_locked'] = false;

    $this->putJson("/api/v1/offers/{$this->offer->id}/form-fields", ['fields' => $fields])
        ->assertUnprocessable();
});

it('isolates form configuration between recruiters', function (): void {
    $other = User::factory()->create();
    $foreign = Offer::factory()->for($other)->create();

    $this->actingAs($other);
    $this->getJson("/api/v1/offers/{$this->offer->id}/form-fields")->assertForbidden();
    $this->putJson("/api/v1/offers/{$foreign->id}/form-fields", ['fields' => lockedFields()])->assertOk();
});
