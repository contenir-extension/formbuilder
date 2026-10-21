<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Override;

/**
 * Renames one of the form's groups.
 */
final class GroupLegendHandler extends AbstractLegendHandler
{
    public const string NAME = 'group-update';

    #[Override]
    protected function has(FormLayout $layout, int $id): bool
    {
        return null !== $layout->group($id);
    }

    #[Override]
    protected function part(): string
    {
        return 'group';
    }

    #[Override]
    protected function rename(Editor $editor, int $id, ?string $legend): void
    {
        $editor->repository->updateGroup($id, ['legend' => $legend]);
    }
}
