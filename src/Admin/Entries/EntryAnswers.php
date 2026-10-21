<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\FormBuilder\Definition\FormDefinition;

use function array_column;
use function array_diff_key;
use function array_is_list;
use function array_map;
use function is_array;
use function is_scalar;
use function json_decode;

/**
 * An entry's stored values by field name: a list for a field holding many
 * (stored as JSON), text for the rest. Values stored under a name the form
 * no longer has, renamed or deleted, are kept apart so they still show.
 */
final readonly class EntryAnswers
{
    /**
     * @param array<string, string|list<string>> $byName
     * @param array<string, string|list<string>> $orphans
     */
    private function __construct(
        public array $byName,
        public array $orphans,
    ) {}

    /**
     * @param list<array<string, mixed>> $values As the repository read them
     */
    public static function of(FormDefinition $form, array $values): self
    {
        $byName = [];
        foreach ($values as $stored) {
            $name = self::text($stored, 'field_name');
            if ('' !== $name) {
                $byName[$name] = self::value($stored);
            }
        }

        return new self($byName, array_diff_key($byName, array_column($form->getAllFields(), null, 'name')));
    }

    /**
     * @param array<string, mixed> $stored
     */
    private static function text(array $stored, string $column): string
    {
        return is_scalar($stored[$column] ?? null) ? (string) $stored[$column] : '';
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return string|list<string>
     *
     * @mago-expect analysis:mixed-assignment A JSON value is untyped until it is checked below.
     */
    private static function value(array $stored): string|array
    {
        $json    = self::text($stored, 'value_json');
        $decoded = '' === $json ? null : json_decode($json, associative: true);
        if (is_array($decoded) && array_is_list($decoded)) {
            return array_map(static fn(mixed $item): string => is_scalar($item) ? (string) $item : '', $decoded);
        }

        return self::text($stored, 'value_text');
    }
}
