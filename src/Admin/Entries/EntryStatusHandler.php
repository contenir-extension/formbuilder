<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Contenir\FormBuilder\Entry\EntryStatusChanger;
use Contenir\FormBuilder\Persistence\PersistenceException;
use DateMalformedStringException;
use Override;
use Psr\Container\ContainerExceptionInterface;

use function array_key_exists;
use function is_string;

/**
 * Moves one of the form's entries to the status its link names (complete,
 * spam or archive), posted from the Entries tab or the entry, and goes back
 * to the Entries tab. An entry held as spam and marked complete has its
 * notifications and webhooks sent then, once.
 */
final class EntryStatusHandler extends AbstractFormHandler
{
    public const string NAME = 'entry-status';

    /**
     * The status the link names, in its address, or else as posted.
     */
    private static function asked(FormRequest $form): string
    {
        $query = $form->request->getQueryParams();

        return is_string($query['status'] ?? null) ? $query['status'] : $form->text('status');
    }

    /**
     * @throws PersistenceException         When the entries can't be read or changed.
     * @throws DateMalformedStringException When the entry's submission time can't be read.
     * @throws ContainerExceptionInterface  When the host can't give its mail.
     */
    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $result = HandlerResult::redirect(Urls::handler(FormTabs::ENTRIES, ['form' => $form->id]));
        $status = self::asked($form);
        $entry  = EntryOf::form($form);
        if (! $form->posts() || null === $entry || ! array_key_exists($status, EntryStatus::CHANGES)) {
            return $result;
        }

        $sent = (new EntryStatusChanger(
            $form->forms->entries,
            EntryNotifiers::of(
                $form->request,
                $form->context,
                $form->form,
                $entry->id,
                $form->forms->entries->fetchValues($entry->id),
            ),
        ))->change($form->form, $entry->id, $status);

        return $result->withMessage(
            'success',
            $sent ? 'Entry marked complete, and its notifications and webhooks sent.' : 'Entry updated.',
        );
    }
}
