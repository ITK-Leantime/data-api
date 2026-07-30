<?php

namespace Leantime\Plugins\APIData\Model;

use Leantime\Plugins\APIData\Services\APIData;

/**
 * The parameters for the deleted-entities endpoint. `types` used to be read
 * without a fallback, so leaving it out was a 500, and an unknown value reached
 * the repository's match arm, which reflected the raw input into the error page.
 */
readonly class DeletedRequestParameters
{
    use CoercesRequestInput;

    /**
     * @param list<string> $types
     */
    public function __construct(
        public array $types,
        public ?int $deleted,
    ) {}

    /**
     * @param array<string, mixed> $input
     */
    public static function fromInput(array $input): self
    {
        return new self(
            types: self::toTypes($input['types'] ?? null),
            deleted: self::toTimestamp($input['deleted'] ?? null, 'deleted'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'types' => $this->types,
            'deleted' => $this->deleted,
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return [
            APIData::TYPE_PROJECTS,
            APIData::TYPE_MILESTONES,
            APIData::TYPE_TICKETS,
            APIData::TYPE_TIMESHEETS,
        ];
    }

    /**
     * @return list<string>
     */
    private static function toTypes(mixed $value): array
    {
        $types = self::toList($value, 'types');

        if ($types === null) {
            return self::supportedTypes();
        }

        foreach ($types as $type) {
            if (!in_array($type, self::supportedTypes(), true)) {
                throw new InvalidRequestException(sprintf(
                    'types must only contain: %s.',
                    implode(', ', self::supportedTypes()),
                ));
            }
        }

        return array_values(array_unique($types));
    }
}
