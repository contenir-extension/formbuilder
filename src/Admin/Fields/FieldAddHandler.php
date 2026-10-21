<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Handler\HandlerResult;
use Override;

/**
 * Adds a field to one of the form's rows: the settings form for the type
 * the request names (a text field when it names none it knows), then, once
 * they check out, the field saved at the row's end.
 */
final class FieldAddHandler extends AbstractEditorHandler
{
    public const string NAME = 'field-add';

    #[Override]
    protected function edit(Editor $editor): HandlerResult
    {
        $row = $editor->layout->row($editor->number('row'))?->id;
        if (null === $row) {
            return $editor->nothing();
        }

        $types = FieldTypes::selectable();
        $form  = new FieldForm(self::NAME, ['row' => $row], 'New field', 'Create field');
        if (! $editor->posts()) {
            $type = Posted::text($editor->request->getQueryParams()['type'] ?? null);

            return $form->show($editor, $types, FieldValues::blank($types->has($type) ? $type : 'text'));
        }

        $input    = FieldInput::fromBody($editor->body(), $types);
        $problems = $input->problems();
        if ([] !== $problems) {
            return $form->show($editor, $types, $input->data, $problems);
        }

        $editor->repository->createField($row, $input->data);

        return FieldForm::saved($editor, 'Field added.');
    }
}
