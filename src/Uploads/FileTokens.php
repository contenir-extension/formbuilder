<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Uploads;

use Closure;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Service\TokenReplacer;

use function in_array;

/**
 * The {file:<field>} merge tag: a link to the file the named upload field
 * holds, which $link makes. A field that isn't one of the form's upload
 * fields leaves the tag as it is, as any unknown tag is left.
 */
final class FileTokens
{
    /**
     * @param Closure(string): string $link The link to the field's file, or empty when it holds none
     */
    public static function register(TokenReplacer $tokens, FormDefinition $form, Closure $link): void
    {
        $uploads = [];
        foreach ($form->getAllFields() as $field) {
            if ('file' !== $field->type) {
                continue;
            }

            $uploads[] = $field->name;
        }

        $tokens->register(
            'file',
            static fn(string $field): ?string => in_array($field, $uploads, strict: true) ? $link($field) : null,
        );
    }
}
