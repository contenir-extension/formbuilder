<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Override;

/**
 * Deletes one of the form's sections, with everything in it.
 */
final class SectionDeleteHandler extends AbstractDeleteHandler
{
    public const string NAME = 'section-delete';

    #[Override]
    protected function delete(Editor $editor, int $id): void
    {
        $editor->repository->deleteSection($id);
    }

    #[Override]
    protected function has(FormLayout $layout, int $id): bool
    {
        return null !== $layout->section($id);
    }

    #[Override]
    protected function message(): string
    {
        return 'Section deleted.';
    }

    #[Override]
    protected function part(): string
    {
        return 'section';
    }
}
