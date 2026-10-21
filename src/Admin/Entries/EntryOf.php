<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\FormBuilder\Persistence\PersistenceException;
use DateMalformedStringException;

/**
 * The entry a request about a form names, as `entry` in its address or its
 * body, when it is one of that form's.
 */
final class EntryOf
{
    /**
     * @throws PersistenceException         When the entry can't be read.
     * @throws DateMalformedStringException When its submission time can't be read.
     */
    public static function form(FormRequest $form): ?EntryRow
    {
        $stored = $form->forms->entries->findEntry($form->number('entry'));
        $entry  = null === $stored ? null : EntryRow::of($stored);

        return $entry?->formId === $form->id ? $entry : null;
    }
}
