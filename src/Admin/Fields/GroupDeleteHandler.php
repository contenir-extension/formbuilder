<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Override;

/**
 * Deletes one of the form's groups, with everything in it.
 */
final class GroupDeleteHandler extends AbstractDeleteHandler
{
    public const string NAME = 'group-delete';

    #[Override]
    protected function delete(Editor $editor, int $id): void
    {
        $editor->repository->deleteGroup($id);
    }

    #[Override]
    protected function has(FormLayout $layout, int $id): bool
    {
        return null !== $layout->group($id);
    }

    #[Override]
    protected function message(): string
    {
        return 'Group deleted.';
    }

    #[Override]
    protected function part(): string
    {
        return 'group';
    }
}
