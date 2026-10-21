<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\FormBuilder\Definition\RowDefinition;
use Override;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;

/**
 * Puts a group's rows in the order the editor's script posts after a row is
 * dragged. Rows that aren't the group's are left out.
 */
final class RowReorderHandler extends AbstractEditorHandler
{
    public const string NAME = 'row-reorder';

    #[Override]
    protected function edit(Editor $editor): HandlerResult
    {
        $group = $editor->layout->group($editor->number('group'));
        if (! $editor->posts() || null === $group?->id) {
            return HandlerResult::json(['ok' => false, 'error' => 'bad_request'], 400);
        }

        $rows = array_map(static fn(RowDefinition $row): ?int => $row->id, $group->rows);

        $editor->repository->reorderRows(
            $group->id,
            array_values(array_filter(
                Posted::numbers(Reorder::rows($editor->body()['rows'] ?? null)),
                static fn(int $id): bool => in_array($id, $rows, strict: true),
            )),
        );

        return HandlerResult::json(['ok' => true]);
    }
}
