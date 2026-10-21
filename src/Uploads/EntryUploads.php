<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Uploads;

use Contenir\FormBuilder\Definition\FormDefinition;

use function in_array;
use function is_string;
use function ltrim;

/**
 * The file each of an entry's upload fields holds, by field name: its path
 * in storage. A value in any other field is not a path, whatever it looks
 * like, and an upload field left empty holds none.
 */
final class EntryUploads
{
    /**
     * @param list<array<string, mixed>> $values The entry's values, as the repository read them
     *
     * @return array<string, string>
     */
    public static function of(FormDefinition $form, array $values): array
    {
        $uploads = [];
        foreach ($form->getAllFields() as $field) {
            if ('file' !== $field->type) {
                continue;
            }

            $uploads[] = $field->name;
        }

        $paths = [];
        foreach ($values as $stored) {
            $name = is_string($stored['field_name'] ?? null) ? $stored['field_name'] : '';
            $path = is_string($stored['value_text'] ?? null) ? ltrim($stored['value_text'], characters: '/ ') : '';
            if ('' !== $path && in_array($name, $uploads, strict: true)) {
                $paths[$name] = $path;
            }
        }

        return $paths;
    }
}
