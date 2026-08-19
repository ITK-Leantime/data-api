<?php

namespace Leantime\Plugins\APIData\Model;

use Leantime\Plugins\APIData\Services\APIData;

/**
 * The parameters for the deleted-entities endpoint. `type` is required and names
 * exactly one type: the endpoint serves a single deletion table per request, and
 * pages through it with `start`/`limit` like the entity endpoints. An unknown
 * value used to reach the repository's match arm, which reflected the raw input
 * into the error page.
 *
 * `start` is a watermark on `deletionId`, the deleted table's own row id, not on
 * the deleted entity's id — deletions are appended, so that is the one column
 * that orders them and never changes under a paging client.
 */
readonly class DeletedRequestParameters
{
    use CoercesRequestInput;

    public function __construct(
        public string $type,
        public int $start,
        public int $limit,
        public ?int $deleted,
    ) {}

    /**
     * @param array<string, mixed> $input
     */
    public static function fromInput(array $input): self
    {
        return new self(
            type: self::toType($input['type'] ?? null),
            start: self::toNonNegativeInt($input['start'] ?? 0, 'start'),
            limit: self::toLimit($input['limit'] ?? self::DEFAULT_LIMIT),
            deleted: self::toTimestamp($input['deleted'] ?? null, 'deleted'),
        );
    }

    /**
     * The effective parameters, so a caller can see the limit it actually got.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'start' => $this->start,
            'limit' => $this->limit,
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

    private static function toType(mixed $value): string
    {
        // A list is rejected rather than reduced to its first element: the
        // endpoint used to accept `types`, and a caller still sending one would
        // otherwise get a page of a type it did not ask about.
        if (is_array($value)) {
            throw new BadRequestException('type must be a single value.');
        }

        $type = is_string($value) ? trim($value) : $value;

        if ($type === null || $type === '') {
            throw new BadRequestException(sprintf(
                'type is required and must be one of: %s.',
                implode(', ', self::supportedTypes()),
            ));
        }

        // Compared strictly, and the input is never echoed back — an unknown type
        // reached the error page verbatim before.
        if (!in_array($type, self::supportedTypes(), true)) {
            throw new BadRequestException(sprintf(
                'type must be one of: %s.',
                implode(', ', self::supportedTypes()),
            ));
        }

        return $type;
    }
}
