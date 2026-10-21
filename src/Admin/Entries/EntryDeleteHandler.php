<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Contenir\FormBuilder\Persistence\PersistenceException;
use DateMalformedStringException;
use Override;

/**
 * Deletes one of the form's entries and its values, posted from the Entries
 * tab or the entry, which asks first, and goes back to the Entries tab.
 */
final class EntryDeleteHandler extends AbstractFormHandler
{
    public const string NAME = 'entry-delete';

    /**
     * @throws PersistenceException         When the entries can't be read or changed.
     * @throws DateMalformedStringException When the entry's submission time can't be read.
     */
    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $result = HandlerResult::redirect(Urls::handler(FormTabs::ENTRIES, ['form' => $form->id]));
        $entry  = EntryOf::form($form);
        if (! $form->posts() || null === $entry) {
            return $result;
        }

        $form->forms->entries->delete($entry->id);

        return $result->withMessage('success', 'Entry deleted.');
    }
}
