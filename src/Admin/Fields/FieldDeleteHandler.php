<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Override;

/**
 * Deletes one of the form's fields.
 */
final class FieldDeleteHandler extends AbstractDeleteHandler
{
    public const string NAME = 'field-delete';

    #[Override]
    protected function delete(Editor $editor, int $id): void
    {
        $editor->repository->deleteField($id);
    }

    #[Override]
    protected function has(FormLayout $layout, int $id): bool
    {
        return null !== $layout->field($id);
    }

    #[Override]
    protected function message(): string
    {
        return 'Field deleted.';
    }

    #[Override]
    protected function part(): string
    {
        return 'field';
    }
}
