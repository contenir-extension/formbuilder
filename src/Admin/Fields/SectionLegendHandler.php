<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Override;

/**
 * Renames one of the form's sections.
 */
final class SectionLegendHandler extends AbstractLegendHandler
{
    public const string NAME = 'section-update';

    #[Override]
    protected function has(FormLayout $layout, int $id): bool
    {
        return null !== $layout->section($id);
    }

    #[Override]
    protected function part(): string
    {
        return 'section';
    }

    #[Override]
    protected function rename(Editor $editor, int $id, ?string $legend): void
    {
        $editor->repository->updateSection($id, ['legend' => $legend]);
    }
}
