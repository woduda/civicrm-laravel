<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Data;

use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Serialisable payload for {@see \CiviCrm\Laravel\Jobs\SyncContactJob}.
 *
 * At least one of `email` or `externalIdentifier` must be supplied — they are
 * the two supported match keys for contact upserts.
 */
final readonly class ContactInput
{
    /**
     * @param array<string, mixed> $extraFields    Custom fields keyed as "GroupName.field_name"
     * @param list<string>         $tags           Tag names to assign after upsert
     * @param list<string>         $groups         Group titles to assign after upsert
     * @param string|null          $contactSubType CiviCRM contact sub-type name (e.g. "Volunteer")
     *
     * @throws ValidationException if neither email nor externalIdentifier is provided
     */
    public function __construct(
        public ?string $externalIdentifier = null,
        public ?string $email = null,
        public string $firstName = '',
        public string $lastName = '',
        public ?string $organizationName = null,
        public array $extraFields = [],
        public array $tags = [],
        public array $groups = [],
        public ?string $contactSubType = null,
    ) {
        if ($this->externalIdentifier === null && $this->email === null) {
            throw new ValidationException(
                'ContactInput requires at least one of: email, externalIdentifier.',
            );
        }
    }

    /**
     * Named constructor from an associative array.
     *
     * Accepted keys: externalIdentifier, email, firstName, lastName,
     * organizationName, extraFields, tags, groups, contactSubType.
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException if neither email nor externalIdentifier is provided
     */
    public static function fromArray(array $data): self
    {
        return new self(
            externalIdentifier: isset($data['externalIdentifier']) && is_string($data['externalIdentifier'])
                ? $data['externalIdentifier']
                : null,
            email: isset($data['email']) && is_string($data['email'])
                ? $data['email']
                : null,
            firstName: isset($data['firstName']) && is_string($data['firstName'])
                ? $data['firstName']
                : '',
            lastName: isset($data['lastName']) && is_string($data['lastName'])
                ? $data['lastName']
                : '',
            organizationName: isset($data['organizationName']) && is_string($data['organizationName'])
                ? $data['organizationName']
                : null,
            extraFields: self::toStringKeyedArray($data['extraFields'] ?? []),
            tags: isset($data['tags']) && is_array($data['tags'])
                ? array_values(array_filter($data['tags'], is_string(...)))
                : [],
            groups: isset($data['groups']) && is_array($data['groups'])
                ? array_values(array_filter($data['groups'], is_string(...)))
                : [],
            contactSubType: isset($data['contactSubType']) && is_string($data['contactSubType'])
                ? $data['contactSubType']
                : null,
        );
    }

    /**
     * Filters an array to only string-keyed entries, preserving mixed values.
     *
     * @return array<string, mixed>
     */
    private static function toStringKeyedArray(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $result = [];

        foreach ($input as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Serialises the DTO to an associative array for outbox storage.
     *
     * This is the inverse of {@see fromArray()} — the round-trip
     * `ContactInput::fromArray($input->toArray())` is lossless.
     *
     * @return array{
     *     externalIdentifier: string|null,
     *     email: string|null,
     *     firstName: string,
     *     lastName: string,
     *     organizationName: string|null,
     *     extraFields: array<string, mixed>,
     *     tags: list<string>,
     *     groups: list<string>,
     *     contactSubType: string|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'externalIdentifier' => $this->externalIdentifier,
            'email'              => $this->email,
            'firstName'          => $this->firstName,
            'lastName'           => $this->lastName,
            'organizationName'   => $this->organizationName,
            'extraFields'        => $this->extraFields,
            'tags'               => $this->tags,
            'groups'             => $this->groups,
            'contactSubType'     => $this->contactSubType,
        ];
    }

    /**
     * Returns the standard CiviCRM contact field values for create/update calls.
     *
     * Excludes email, externalIdentifier, tags, groups, and extraFields — those are
     * applied separately by the job after the initial upsert.
     *
     * @return array<string, mixed>
     */
    public function toCiviValues(): array
    {
        $values = ['contact_type' => 'Individual'];

        if ($this->firstName !== '') {
            $values['first_name'] = $this->firstName;
        }

        if ($this->lastName !== '') {
            $values['last_name'] = $this->lastName;
        }

        if ($this->organizationName !== null) {
            $values['organization_name'] = $this->organizationName;
        }

        if ($this->contactSubType !== null) {
            $values['contact_sub_type'] = $this->contactSubType;
        }

        return $values;
    }
}
