<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Override;

/**
 * Deletes one of the form's rows, with everything in it.
 */
final class RowDeleteHandler extends AbstractDeleteHandler
{
    public const string NAME = 'row-delete';

    #[Override]
    protected function delete(Editor $editor, int $id): void
    {
        $editor->repository->deleteRow($id);
    }

    #[Override]
    protected function has(FormLayout $layout, int $id): bool
    {
        return null !== $layout->row($id);
    }

    #[Override]
    protected function message(): string
    {
        return 'Row deleted.';
    }

    #[Override]
    protected function part(): string
    {
        return 'row';
    }
}
