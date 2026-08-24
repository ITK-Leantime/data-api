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

    /**
     * Create the validated parameter set for the deleted endpoint.
     */
    public function __construct(
        public string $type,
        public int $start,
        public int $limit,
        public ?int $deletedAfter,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromInput(array $input): self
    {
        self::rejectTheOldTypesName($input);
        self::rejectTheOldDeletedName($input);

        return new self(
            type: self::toType($input['type'] ?? null),
            start: self::toNonNegativeInt($input['start'] ?? 0, 'start'),
            limit: self::toLimit($input['limit'] ?? self::DEFAULT_LIMIT),
            deletedAfter: self::toTimestamp($input['deletedAfter'] ?? null, 'deletedAfter'),
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
            'deletedAfter' => $this->deletedAfter,
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
     * `types` took a list until the endpoint was narrowed to serving one type per
     * request. A caller still sending it has no `type` at all, so without this it
     * would be turned away by the generic "type is required" — true, but silent
     * about the one thing it needs to change.
     *
     * @param array<string, mixed> $input
     */
    private static function rejectTheOldTypesName(array $input): void
    {
        if (self::wasSent($input['types'] ?? null)) {
            throw new BadRequestException('types has been replaced by type, which names exactly one type.');
        }
    }

    /**
     * `deleted` was this parameter's name until it was aligned with the entity
     * endpoints' `modifiedAfter`. Ignoring it would answer with the whole deletion
     * history while the caller believes it asked for a window — which is exactly
     * how the consumer's timestamp went missing when it sent the other name.
     *
     * @param array<string, mixed> $input
     */
    private static function rejectTheOldDeletedName(array $input): void
    {
        if (self::wasSent($input['deleted'] ?? null)) {
            throw new BadRequestException('deleted has been renamed to deletedAfter.');
        }
    }

    /**
     * A retired parameter is only worth failing over when it carries something.
     * An empty value means it was never really sent — a bare `?deleted=` or
     * `?types[]=` is what a query string leaves behind, not a request for the old
     * behaviour.
     */
    private static function wasSent(mixed $value): bool
    {
        if (is_array($value)) {
            return [] !== array_filter($value, fn ($element) => $element !== null && $element !== '');
        }

        return $value !== null && $value !== '';
    }

    /**
     * Narrow the requested type to one of the supported entity types.
     */
    private static function toType(mixed $value): string
    {
        // `type[]=tickets&type[]=timesheets` is rejected rather than reduced to
        // its first element, which would answer with a page of a type the caller
        // did not ask about. The retired `types` name is caught earlier.
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
