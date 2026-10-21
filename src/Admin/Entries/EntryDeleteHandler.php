<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Contenir\Extension\Formbuilder\Uploads\UploadStorage;
use Contenir\FormBuilder\Persistence\PersistenceException;
use DateMalformedStringException;
use Override;
use Psr\Container\ContainerExceptionInterface;

/**
 * Deletes one of the form's entries, its values and its uploaded files,
 * posted from the Entries tab or the entry, which asks first, and goes back
 * to the Entries tab. When a file can't be deleted the entry is kept, and
 * the tab says so.
 */
final class EntryDeleteHandler extends AbstractFormHandler
{
    public const string NAME = 'entry-delete';

    /**
     * @throws PersistenceException         When the entries can't be read or changed.
     * @throws DateMalformedStringException When the entry's submission time can't be read.
     * @throws ContainerExceptionInterface  When the host can't give its storage.
     */
    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $result = HandlerResult::redirect(Urls::handler(FormTabs::ENTRIES, ['form' => $form->id]));
        $entry  = EntryOf::form($form);
        if (! $form->posts() || null === $entry) {
            return $result;
        }

        $storage = UploadStorage::of($form->context);
        $values  = $form->forms->entries->fetchValues($entry->id);
        if (null !== $storage && ! $storage->delete($form->form, $entry->id, $values)) {
            return $result->withMessage(
                'danger',
                'Its files could not be deleted, so the entry was kept. The log has the details.',
            );
        }

        $form->forms->entries->delete($entry->id);

        return $result->withMessage('success', 'Entry deleted.');
    }
}
