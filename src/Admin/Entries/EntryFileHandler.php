<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Contenir\Extension\Formbuilder\Uploads\EntryFile;
use Contenir\Extension\Formbuilder\Uploads\UploadStorage;
use Contenir\FormBuilder\Persistence\PersistenceException;
use DateMalformedStringException;
use Override;
use Psr\Container\ContainerExceptionInterface;

use function is_string;

/**
 * The file one of an entry's upload fields holds, sent to someone viewing
 * the entry, from private storage or, until it is moved, public. A file
 * that has gone goes back to the entry saying so; an entry that isn't the
 * form's goes back to the Entries tab.
 */
final class EntryFileHandler extends AbstractFormHandler
{
    public const string NAME = 'entry-file';

    /**
     * @throws PersistenceException         When the entry can't be read.
     * @throws DateMalformedStringException When the entry's submission time can't be read.
     * @throws ContainerExceptionInterface  When the host can't give its storage.
     */
    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $entry = EntryOf::form($form);
        if (null === $entry) {
            return HandlerResult::redirect(Urls::handler(FormTabs::ENTRIES, ['form' => $form->id]));
        }

        $query = $form->request->getQueryParams();
        $file  = EntryFile::of(
            $form->forms,
            UploadStorage::of($form->context),
            $form->form,
            $entry->id,
            is_string($query['field'] ?? null) ? $query['field'] : '',
        );

        return null === $file
            ? HandlerResult::redirect(Urls::handler(EntryHandler::NAME, ['form' => $form->id, 'entry' => $entry->id]))
                ->withMessage('danger', 'That file is no longer in storage.')
            : HandlerResult::download($file->name, 'application/octet-stream', $file->writer);
    }
}
