<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Override;

/**
 * Adds an empty section, group or row at the position the request names,
 * inside a part of the form it names. The editor's script gets the new
 * part drawn alone, to put in place; a page goes back to the fields screen.
 *
 * @internal
 */
abstract class AbstractInsertHandler extends AbstractEditorHandler
{
    /**
     * The new part's id, or null when the request names no part of the form
     * to add it to.
     *
     * @throws PersistenceException When it can't be added.
     */
    abstract protected function insert(Editor $editor, int $position): ?int;

    /**
     * What the fields screen says once it is added.
     */
    abstract protected function message(): string;

    /**
     * The template that draws the new part, and its values.
     *
     * @return array{string, array<string, mixed>}
     */
    abstract protected function part(FormLayout $layout, int $id): array;

    #[Override]
    protected function edit(Editor $editor): HandlerResult
    {
        if (! $editor->posts()) {
            return $editor->back();
        }

        $id = $this->insert($editor, $editor->number('position'));
        if (null === $id) {
            return $editor->nothing();
        }

        if (! $editor->scripted()) {
            return $editor->back($this->message());
        }

        [$template, $values] = $this->part($editor->reloaded(), $id);

        return HandlerResult::fragment($template, $values + ['types' => FieldTypes::selectable()]);
    }
}
