<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Override;

/**
 * Adds an empty row to one of the form's groups.
 */
final class RowInsertHandler extends AbstractInsertHandler
{
    public const string NAME = 'row-insert';

    #[Override]
    protected function insert(Editor $editor, int $position): ?int
    {
        $group = $editor->layout->group($editor->number('group'))?->id;

        return null === $group ? null : $editor->repository->insertRow($group, $position);
    }

    #[Override]
    protected function message(): string
    {
        return 'Row inserted.';
    }

    #[Override]
    protected function part(FormLayout $layout, int $id): array
    {
        return ['forms/fields/row', ['form' => $layout->form, 'row' => $layout->row($id)]];
    }
}
