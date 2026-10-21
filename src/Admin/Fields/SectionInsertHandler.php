<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Override;

/**
 * Adds an empty section to the form.
 */
final class SectionInsertHandler extends AbstractInsertHandler
{
    public const string NAME = 'section-insert';

    #[Override]
    protected function insert(Editor $editor, int $position): int
    {
        return $editor->repository->insertSection($editor->layout->id, $position);
    }

    #[Override]
    protected function message(): string
    {
        return 'Section inserted.';
    }

    #[Override]
    protected function part(FormLayout $layout, int $id): array
    {
        return ['forms/fields/section', ['form' => $layout->form, 'section' => $layout->section($id)]];
    }
}
