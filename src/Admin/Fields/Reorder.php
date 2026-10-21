<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use JsonException;

use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * The order the editor's script posts after a drag, as JSON in its rows
 * field: a group's row ids, or each row's field ids by row id.
 */
final class Reorder
{
    /**
     * The JSON the script posted, decoded; empty when it isn't a JSON list
     * or object.
     *
     * @return array<array-key, mixed>
     */
    public static function rows(mixed $posted): array
    {
        if (! is_string($posted)) {
            return [];
        }

        try {
            return self::listed(json_decode($posted, associative: true, flags: JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function listed(mixed $decoded): array
    {
        return is_array($decoded) ? $decoded : [];
    }
}
