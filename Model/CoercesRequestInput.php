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
    private static function toNonNegativeInt(mixed $value, string $name): int
    {
        if (!is_int($value) && !(is_string($value) && is_numeric($value))) {
            throw new InvalidRequestException(sprintf('%s must be a whole number.', $name));
        }

        if ((float) $value !== (float) (int) $value) {
            throw new InvalidRequestException(sprintf('%s must be a whole number.', $name));
        }

        if ((int) $value < 0) {
            throw new InvalidRequestException(sprintf('%s cannot be negative.', $name));
        }

        return (int) $value;
    }

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
            foreach ($value as $element) {
                if (!is_scalar($element)) {
                    throw new InvalidRequestException(sprintf('%s must be a list of values.', $name));
                }
            }

            return array_values($value);
        }

        if (!is_scalar($value)) {
            throw new InvalidRequestException(sprintf('%s must be a list of values.', $name));
        }

        $elements = array_filter(
            array_map('trim', explode(',', (string) $value)),
            fn ($element) => $element !== '',
        );

        return [] === $elements ? null : array_values($elements);
    }
}
