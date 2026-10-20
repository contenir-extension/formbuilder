<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\TestAsset;

use Contenir\FormBuilder\Definition\FormDefinition;

use function count;

/**
 * What a stored form's layout holds, for tests to compare.
 */
final class FormShape
{
    /**
     * How many sections, groups in the first, rows in its first, and fields in that row's first.
     *
     * @return list<int>
     */
    public static function counts(?FormDefinition $form): array
    {
        $group = $form->sections[0]->groups[0] ?? null;

        return [
            count($form->sections ?? []),
            count($form->sections[0]->groups ?? []),
            count($group->rows ?? []),
            count($group->rows[0]->fields ?? []),
        ];
    }

    /**
     * Its fields' names, in order.
     *
     * @return list<string>
     */
    public static function names(?FormDefinition $form): array
    {
        $names = [];
        foreach ($form->sections ?? [] as $section) {
            foreach ($section->groups as $group) {
                foreach ($group->rows as $row) {
                    foreach ($row->fields as $field) {
                        $names[] = $field->name;
                    }
                }
            }
        }

        return $names;
    }
}
