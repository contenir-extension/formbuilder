<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\FormBuilder\Html\FormContentSanitizer;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function in_array;

/**
 * A field's type options as the field form posts them: a choice field's
 * values paired with their labels (a choice without a value left out, one
 * without a label labelled by its value), a content field's HTML sanitised,
 * and empty options left out.
 */
final class FieldOptions
{
    private const array CHOICE_TYPES = ['select', 'multiselect', 'multicheckbox', 'radio'];

    /**
     * @param array<array-key, mixed> $options
     *
     * @return array<array-key, mixed>
     */
    public static function of(string $type, array $options): array
    {
        if (in_array($type, self::CHOICE_TYPES, strict: true)) {
            $options['choices'] = self::choices(
                Posted::items($options['choice_value'] ?? []),
                Posted::items($options['choice_label'] ?? []),
            );
            unset($options['choice_value'], $options['choice_label']);
        }

        if ('content' === $type && array_key_exists('html', $options)) {
            $options['html'] = FormContentSanitizer::sanitize(Posted::text($options['html']));
        }

        return array_filter($options, static fn(mixed $value): bool => '' !== $value && null !== $value);
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<array-key, mixed> $labels
     *
     * @return list<array{value: string, label: string}>
     */
    private static function choices(array $values, array $labels): array
    {
        $choices = [];
        foreach (array_keys($values) as $index) {
            $value = Posted::text($values[$index] ?? null);
            if ('' === $value) {
                continue;
            }

            $label     = Posted::text($labels[$index] ?? '');
            $choices[] = ['value' => $value, 'label' => '' === $label ? $value : $label];
        }

        return $choices;
    }
}
