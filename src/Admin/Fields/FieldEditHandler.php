<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Handler\HandlerResult;
use Override;

/**
 * Edits one of the form's fields: its settings form, then, once they check
 * out, the field saved. Its type stays the one it was made with, whatever
 * is posted.
 */
final class FieldEditHandler extends AbstractEditorHandler
{
    public const string NAME = 'field-edit';

    #[Override]
    protected function edit(Editor $editor): HandlerResult
    {
        $field = $editor->layout->field($editor->number('field'));
        $id    = $field?->id;
        if (null === $field || null === $id) {
            return $editor->nothing();
        }

        $types = FieldTypes::selectable();
        $form  = new FieldForm(self::NAME, ['field' => $id], 'Edit field', 'Save field', $id);
        if (! $editor->posts()) {
            return $form->show($editor, $types, FieldValues::of($field));
        }

        $input    = FieldInput::fromBody($editor->body(), $types)->withType($field->type);
        $problems = $input->problems();
        if ([] !== $problems) {
            return $form->show($editor, $types, $input->data, $problems);
        }

        $editor->repository->updateField($id, $input->data);

        return FieldForm::saved($editor, 'Field saved.');
    }
}
