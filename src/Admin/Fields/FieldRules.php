<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function is_scalar;
use function trim;

/**
 * A field's validation rules as the field form posts them: the rules
 * ticked, in the order posted, each with the options it was given (empty
 * ones left out) and its message, if any.
 */
final class FieldRules
{
    /**
     * @return list<array{type: string, options: array<array-key, mixed>, message: string|null}>
     */
    public static function of(mixed $posted): array
    {
        return array_values(array_filter(array_map(
            self::rule(...),
            is_array($posted) ? $posted : [],
        )));
    }

    /**
     * A rule's options, empty ones left out.
     *
     * @return array<array-key, mixed>
     */
    private static function options(mixed $options): array
    {
        return array_filter(
            is_array($options) ? $options : [],
            static fn(mixed $value): bool => ! is_scalar($value) || '' !== trim((string) $value),
        );
    }

    /**
     * @return array{type: string, options: array<array-key, mixed>, message: string|null}|null
     */
    private static function rule(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $type = Posted::text($entry['type'] ?? '');
        if ('' === $type) {
            return null;
        }

        return [
            'type'    => $type,
            'options' => self::options($entry['options'] ?? null),
            'message' => Posted::optional($entry['message'] ?? null),
        ];
    }
}
