<?php

namespace Leantime\Plugins\APIData\Model;

/**
 * The parameters shared by the entity endpoints, validated up front so a
 * malformed request is a 400 here rather than a 500 further down — an `ids`
 * scalar reaches count() inside the query builder, and a negative limit is
 * ignored by Laravel, which drops the LIMIT clause and returns the whole table.
 */
readonly class RequestParameters
{
    use CoercesRequestInput;

    /**
     * @param list<int>|null $ids
     * @param list<int>|null $projectIds
     */
    public function __construct(
        public int $start,
        public int $limit,
        public ?int $modifiedAfter,
        public ?array $ids,
        public ?array $projectIds,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromInput(array $input): self
    {
        return new self(
            start: self::toNonNegativeInt($input['start'] ?? 0, 'start'),
            limit: self::toLimit($input['limit'] ?? self::DEFAULT_LIMIT),
            modifiedAfter: self::toTimestamp($input['modifiedAfter'] ?? null, 'modifiedAfter'),
            ids: self::toIds($input['ids'] ?? null, 'ids'),
            projectIds: self::toIds($input['projectIds'] ?? null, 'projectIds'),
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
            'start' => $this->start,
            'limit' => $this->limit,
            'modifiedAfter' => $this->modifiedAfter,
            'ids' => $this->ids,
            'projectIds' => $this->projectIds,
        ];
    }

    /**
     * @return list<int>|null
     */
    private static function toIds(mixed $value, string $name): ?array
    {
        $ids = self::toList($value, $name);

        if ($ids === null) {
            return null;
        }

        return array_map(fn ($id) => self::toNonNegativeInt($id, $name), $ids);
    }
}
