<?php

namespace Leantime\Plugins\APIData\Model;

use Leantime\Plugins\APIData\Services\APIData;

/**
 * The parameters for the deleted-entities endpoint. `types` is required: the
 * endpoint has no limit, so each type returns its whole deletion history, and
 * defaulting it would let a bare request scan every table. An unknown value used
 * to reach the repository's match arm, which reflected the raw input into the
 * error page.
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

        // An empty list is rejected along with a missing one: it would otherwise
        // answer 200 with nothing, which a caller reads as "nothing was deleted".
        if ($types === null || $types === []) {
            throw new BadRequestException(sprintf(
                'types is required and must contain at least one of: %s.',
                implode(', ', self::supportedTypes()),
            ));
        }

        foreach ($types as $type) {
            if (!in_array($type, self::supportedTypes(), true)) {
                throw new BadRequestException(sprintf(
                    'types must only contain: %s.',
                    implode(', ', self::supportedTypes()),
                ));
            }
        }

        return array_values(array_unique($types));
    }
}
