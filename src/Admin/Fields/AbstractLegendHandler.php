<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Override;

/**
 * Renames a section or group in place: its posted legend, trimmed, or none
 * when that is empty. The editor's script gets the legend saved; a page
 * goes back to the fields screen.
 *
 * @internal
 */
abstract class AbstractLegendHandler extends AbstractEditorHandler
{
    /**
     * Whether the form has the part.
     */
    abstract protected function has(FormLayout $layout, int $id): bool;

    /**
     * The request's name for the part's id: section or group.
     */
    abstract protected function part(): string;

    /**
     * @throws PersistenceException When it can't be renamed.
     */
    abstract protected function rename(Editor $editor, int $id, ?string $legend): void;

    #[Override]
    protected function edit(Editor $editor): HandlerResult
    {
        $id = $editor->number($this->part());
        if (! $editor->posts() || ! $this->has($editor->layout, $id)) {
            return $editor->nothing();
        }

        $legend = Posted::optional($editor->body()['legend'] ?? null);
        $this->rename($editor, $id, $legend);

        return $editor->scripted() ? HandlerResult::json(['ok' => true, 'legend' => $legend]) : $editor->back();
    }
}
