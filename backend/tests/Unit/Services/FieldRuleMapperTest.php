<?php

declare(strict_types=1);

use App\Enums\FieldType;
use App\Models\OfferFormField;
use App\Services\Applications\FieldRuleMapper;

beforeEach(function (): void {
    $this->mapper = app(FieldRuleMapper::class);
});

function makeField(string $type, bool $required = false, ?array $options = null): OfferFormField
{
    return new OfferFormField([
        'key' => 'test_field',
        'label' => 'Test',
        'type' => $type,
        'is_required' => $required,
        'options' => $options,
    ]);
}

it('maps text fields with length limits', function (): void {
    expect($this->mapper->rulesFor(makeField(FieldType::Text->value)))->toBe(['nullable', 'string', 'max:255'])
        ->and($this->mapper->rulesFor(makeField(FieldType::Text->value, true)))->toBe(['required', 'string', 'max:255']);
});

it('maps file fields to CV constraints and images to photo constraints', function (): void {
    expect($this->mapper->rulesFor(makeField(FieldType::File->value, true)))
        ->toBe(['required', 'file', 'mimes:pdf,docx', 'max:5120'])
        ->and($this->mapper->rulesFor(makeField(FieldType::Image->value)))
        ->toBe(['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048']);
});

it('maps select fields to option lists', function (): void {
    expect($this->mapper->rulesFor(makeField(FieldType::Select->value, true, ['a', 'b'])))
        ->toBe(['required', 'string', 'in:a,b']);
});

it('detects file fields', function (): void {
    expect($this->mapper->isFileField(makeField(FieldType::File->value)))->toBeTrue()
        ->and($this->mapper->isFileField(makeField(FieldType::Image->value)))->toBeTrue()
        ->and($this->mapper->isFileField(makeField(FieldType::Text->value)))->toBeFalse();
});
