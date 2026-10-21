<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Override;

/**
 * A form's Fields tab: its sections as cards holding groups, rows and
 * fields, arranged by dragging, added and removed in place and edited in
 * the field modal, by the add-on's scripts.
 */
final class FieldsHandler extends AbstractEditorHandler
{
    public const string NAME = FormTabs::FIELDS;

    #[Override]
    protected function edit(Editor $editor): HandlerResult
    {
        $layout = $editor->layout;

        return HandlerResult::template(
            'forms/fields',
            $layout->form->title,
            ['form' => $layout->form, 'types' => FieldTypes::selectable()],
            FormTabs::of($layout->id, self::NAME),
        );
    }
}
