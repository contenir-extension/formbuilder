<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\FormBuilder\Definition\FieldDefinition;

/**
 * A field's settings as the field form shows them: a new field's, or a
 * saved one's.
 */
final class FieldValues
{
    /**
     * A new field of $type, its label shown and nothing else set.
     *
     * @return array<string, mixed>
     */
    public static function blank(string $type = 'text'): array
    {
        return [
            'type'          => $type,
            'name'          => '',
            'label'         => null,
            'show_label'    => true,
            'description'   => null,
            'placeholder'   => null,
            'default_value' => null,
            'required'      => false,
            'options'       => [],
            'validators'    => [],
            'filters'       => [],
            'conditional'   => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function of(FieldDefinition $field): array
    {
        return [
            'type'          => $field->type,
            'name'          => $field->name,
            'label'         => $field->label,
            'show_label'    => $field->showLabel,
            'description'   => $field->description,
            'placeholder'   => $field->placeholder,
            'default_value' => $field->defaultValue,
            'required'      => $field->required,
            'options'       => $field->options,
            'validators'    => self::rules($field),
            'filters'       => $field->filters,
            'conditional'   => $field->conditional,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rules(FieldDefinition $field): array
    {
        $rules = [];
        foreach ($field->validators as $validator) {
            $rules[] = $validator->toArray();
        }

        return $rules;
    }
}
