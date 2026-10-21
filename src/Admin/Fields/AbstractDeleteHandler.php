<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Override;

/**
 * Deletes a section, group, row or field of the form, with everything in
 * it. The editor's script learns it is gone, to take it off the page; a
 * page goes back to the fields screen. A part of another form, or none,
 * deletes nothing.
 *
 * @internal
 */
abstract class AbstractDeleteHandler extends AbstractEditorHandler
{
    /**
     * @throws PersistenceException When it can't be deleted.
     */
    abstract protected function delete(Editor $editor, int $id): void;

    /**
     * Whether the form has the part.
     */
    abstract protected function has(FormLayout $layout, int $id): bool;

    /**
     * What the fields screen says once it is deleted.
     */
    abstract protected function message(): string;

    /**
     * The request's name for the part's id: section, group, row or field.
     */
    abstract protected function part(): string;

    #[Override]
    protected function edit(Editor $editor): HandlerResult
    {
        $id = $editor->number($this->part());
        if (! $editor->posts() || ! $this->has($editor->layout, $id)) {
            return $editor->nothing();
        }

        $this->delete($editor, $id);

        return (
            $editor->scripted()
                ? HandlerResult::json(['ok' => true, "{$this->part()}_id" => $id])
                : $editor->back($this->message())
        );
    }
}
