<?php

declare(strict_types=1);

use CiviCrm\Laravel\Data\ContactInput;
use Woduda\CiviCRM\Exception\ValidationException;

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

it('accepts email only', function (): void {
    $input = new ContactInput(email: 'jane@example.org');

    expect($input->email)->toBe('jane@example.org')
        ->and($input->externalIdentifier)->toBeNull();
});

it('accepts externalIdentifier only', function (): void {
    $input = new ContactInput(externalIdentifier: 'ext-123');

    expect($input->externalIdentifier)->toBe('ext-123')
        ->and($input->email)->toBeNull();
});

it('accepts both email and externalIdentifier', function (): void {
    $input = new ContactInput(externalIdentifier: 'ext-1', email: 'a@b.com');

    expect($input->externalIdentifier)->toBe('ext-1')
        ->and($input->email)->toBe('a@b.com');
});

it('throws ValidationException when neither email nor externalIdentifier is provided', function (): void {
    expect(fn() => new ContactInput())->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------------
// fromArray
// ---------------------------------------------------------------------------

it('constructs from array with email', function (): void {
    $input = ContactInput::fromArray(['email' => 'a@b.com']);

    expect($input->email)->toBe('a@b.com');
});

it('constructs from array with externalIdentifier', function (): void {
    $input = ContactInput::fromArray(['externalIdentifier' => 'ext-99']);

    expect($input->externalIdentifier)->toBe('ext-99');
});

it('throws ValidationException from fromArray when match key is absent', function (): void {
    expect(fn() => ContactInput::fromArray(['firstName' => 'Jane']))->toThrow(ValidationException::class);
});

it('preserves tags and groups from fromArray', function (): void {
    $input = ContactInput::fromArray([
        'email'  => 'a@b.com',
        'tags'   => ['Donor', 'VIP'],
        'groups' => ['Newsletter'],
    ]);

    expect($input->tags)->toBe(['Donor', 'VIP'])
        ->and($input->groups)->toBe(['Newsletter']);
});

it('preserves extraFields from fromArray', function (): void {
    $input = ContactInput::fromArray([
        'email'       => 'a@b.com',
        'extraFields' => ['MyGroup.field' => 'val'],
    ]);

    expect($input->extraFields)->toBe(['MyGroup.field' => 'val']);
});

// ---------------------------------------------------------------------------
// toCiviValues
// ---------------------------------------------------------------------------

it('always includes contact_type Individual', function (): void {
    $input = new ContactInput(email: 'a@b.com');

    expect($input->toCiviValues())->toHaveKey('contact_type', 'Individual');
});

it('includes first_name and last_name when non-empty', function (): void {
    $input = new ContactInput(email: 'a@b.com', firstName: 'Jane', lastName: 'Doe');

    expect($input->toCiviValues())
        ->toHaveKey('first_name', 'Jane')
        ->toHaveKey('last_name', 'Doe');
});

it('omits first_name and last_name when empty string', function (): void {
    $input = new ContactInput(email: 'a@b.com');
    $values = $input->toCiviValues();

    expect($values)->not->toHaveKey('first_name');
    expect($values)->not->toHaveKey('last_name');
});

it('includes organization_name when set', function (): void {
    $input = new ContactInput(email: 'a@b.com', organizationName: 'ACME');

    expect($input->toCiviValues())->toHaveKey('organization_name', 'ACME');
});

it('includes contact_sub_type in toCiviValues when set', function (): void {
    $input = new ContactInput(email: 'a@b.com', contactSubType: 'Wolontariusz');

    expect($input->toCiviValues())->toHaveKey('contact_sub_type', 'Wolontariusz');
});

it('omits contact_sub_type from toCiviValues when null', function (): void {
    $input = new ContactInput(email: 'a@b.com');

    expect($input->toCiviValues())->not->toHaveKey('contact_sub_type');
});

it('parses contactSubType from fromArray', function (): void {
    $input = ContactInput::fromArray(['email' => 'a@b.com', 'contactSubType' => 'Wolontariusz']);

    expect($input->contactSubType)->toBe('Wolontariusz');
});

it('contactSubType is null when absent from fromArray', function (): void {
    $input = ContactInput::fromArray(['email' => 'a@b.com']);

    expect($input->contactSubType)->toBeNull();
});

it('toArray round-trip preserves contactSubType', function (): void {
    $input = new ContactInput(email: 'a@b.com', contactSubType: 'Wolontariusz');

    expect(ContactInput::fromArray($input->toArray())->contactSubType)->toBe('Wolontariusz');
});

it('does not include email externalIdentifier tags groups or extraFields in toCiviValues', function (): void {
    $input = new ContactInput(
        externalIdentifier: 'ext-1',
        email: 'a@b.com',
        extraFields: ['G.f' => 'v'],
        tags: ['Donor'],
        groups: ['NL'],
    );

    $values = $input->toCiviValues();

    expect($values)->not->toHaveKey('email');
    expect($values)->not->toHaveKey('external_identifier');
    expect($values)->not->toHaveKey('tags');
    expect($values)->not->toHaveKey('groups');
    expect($values)->not->toHaveKey('extraFields');
});
