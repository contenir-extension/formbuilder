<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use function array_filter;
use function array_map;
use function array_values;
use function intval;
use function is_array;
use function is_numeric;
use function is_scalar;
use function trim;

/**
 * What a request posted or asked for, read as the editor expects it:
 * anything else reads as nothing.
 */
final class Posted
{
    /**
     * $value as a list, a single value as a list of one.
     *
     * @return array<array-key, mixed>
     */
    public static function items(mixed $value): array
    {
        return is_array($value) ? $value : [$value];
    }

    /**
     * $value as a whole number; zero when it isn't one.
     */
    public static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * The whole numbers $value lists, in order, anything else left out.
     *
     * @return list<int>
     */
    public static function numbers(mixed $value): array
    {
        return array_values(array_map(intval(...), array_filter(is_array($value) ? $value : [], is_numeric(...))));
    }

    /**
     * $value as trimmed text, or null when that is empty.
     */
    public static function optional(mixed $value): ?string
    {
        $text = self::text($value);

        return '' === $text ? null : $text;
    }

    /**
     * $value as trimmed text; empty when it isn't text.
     */
    public static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Whether a checkbox was ticked: anything but an empty value or "0".
     */
    public static function ticked(mixed $value): bool
    {
        return is_scalar($value) && (bool) $value;
    }
}
