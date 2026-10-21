<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Handler\HandlerResult;
use Override;

use function array_keys;

/**
 * Puts the form's fields in the rows and order the editor's script posts
 * after a field is dragged, within a row or across rows and groups. Rows
 * and fields that aren't the form's are left out.
 */
final class FieldReorderHandler extends AbstractEditorHandler
{
    public const string NAME = 'field-reorder';

    #[Override]
    protected function edit(Editor $editor): HandlerResult
    {
        $posted = Reorder::rows($editor->body()['rows'] ?? null);
        if (! $editor->posts() || [] === $posted) {
            return HandlerResult::json(['ok' => false, 'error' => 'bad_request'], 400);
        }

        $layout = $editor->layout;
        $rows   = [];
        foreach (array_keys($posted) as $row) {
            if (null === $layout->row((int) $row)) {
                continue;
            }

            $fields = [];
            foreach (Posted::numbers($posted[$row] ?? null) as $field) {
                if (null === $layout->field($field)) {
                    continue;
                }

                $fields[] = $field;
            }

            $rows[(int) $row] = $fields;
        }

        $editor->repository->reorderFields($rows);

        return HandlerResult::json(['ok' => true]);
    }
}
