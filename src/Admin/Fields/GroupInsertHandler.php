<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Override;

/**
 * Adds an empty group to one of the form's sections.
 */
final class GroupInsertHandler extends AbstractInsertHandler
{
    public const string NAME = 'group-insert';

    #[Override]
    protected function insert(Editor $editor, int $position): ?int
    {
        $section = $editor->layout->section($editor->number('section'))?->id;

        return null === $section ? null : $editor->repository->insertGroup($section, $position);
    }

    #[Override]
    protected function message(): string
    {
        return 'Group inserted.';
    }

    #[Override]
    protected function part(FormLayout $layout, int $id): array
    {
        return ['forms/fields/group', ['form' => $layout->form, 'group' => $layout->group($id)]];
    }
}
