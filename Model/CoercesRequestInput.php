<?php

namespace Leantime\Plugins\APIData\Model;

/**
 * Shared coercions for the request parameter objects. Everything arrives as a
 * string when the endpoints are called with query parameters, so each value has
 * to be narrowed explicitly rather than cast — a plain (int) cast turns "abc"
 * into 0, which silently means "from the beginning of time" for a timestamp.
 */
trait CoercesRequestInput
{
    public const DEFAULT_LIMIT = 100;
    public const MAX_LIMIT = 1000;

    /**
     * A limit below 1 is rejected rather than clamped; above the maximum it is
     * capped silently, and `toArray()` echoes what was actually applied.
     */
    private static function toLimit(mixed $value): int
    {
        $limit = self::toNonNegativeInt($value, 'limit');

        if ($limit < 1) {
            throw new BadRequestException('limit must be at least 1.');
        }

        return min($limit, self::MAX_LIMIT);
    }

    /**
     * Narrow a parameter to a whole number of zero or more.
     */
    private static function toNonNegativeInt(mixed $value, string $name): int
    {
        if (!is_int($value) && !(is_string($value) && is_numeric($value))) {
            throw new BadRequestException(sprintf('%s must be a whole number.', $name));
        }

        if ((float) $value !== (float) (int) $value) {
            throw new BadRequestException(sprintf('%s must be a whole number.', $name));
        }

        if ((int) $value < 0) {
            throw new BadRequestException(sprintf('%s cannot be negative.', $name));
        }

        return (int) $value;
    }

    /**
     * Narrow a unix timestamp parameter, treating absent and empty as no bound.
     */
    private static function toTimestamp(mixed $value, string $name): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::toNonNegativeInt($value, $name);
    }

    /**
     * The README documents GET with query parameters, so a list commonly arrives
     * as "1,2,3" rather than ids[]=1&ids[]=2. An empty array is kept as an empty
     * list — the caller asked for a list containing nothing — while an empty
     * string means the parameter was never really sent.
     *
     * @return list<mixed>|null
     */
    private static function toList(mixed $value, string $name): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $elements = [];

            foreach ($value as $element) {
                if (!is_scalar($element)) {
                    throw new BadRequestException(sprintf('%s must be a list of values.', $name));
                }

                // Trimmed like the comma separated form, so ?types[]=tickets%20
                // is not a 400 while ?types=tickets,%20timesheets works. Only
                // strings, to leave a JSON body's integers as integers.
                $elements[] = is_string($element) ? trim($element) : $element;
            }

            return $elements;
        }

        if (!is_scalar($value)) {
            throw new BadRequestException(sprintf('%s must be a list of values.', $name));
        }

        $elements = array_filter(
            array_map('trim', explode(',', (string) $value)),
            fn ($element) => $element !== '',
        );

        return [] === $elements ? null : array_values($elements);
    }
}
