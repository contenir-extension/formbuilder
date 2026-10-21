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
 * One entry, under its form's Entries tab: when and from where it was
 * submitted and its status, then its answers, a card per group of the form
 * as it stands, and any values stored under fields the form no longer has,
 * with Back to entries, the status changes and Delete. An entry that isn't
 * the form's goes back to the Entries tab.
 */
final class EntryHandler extends AbstractFormHandler
{
    public const string NAME = 'entry';

    /**
     * @throws PersistenceException         When the entries can't be read or changed.
     * @throws DateMalformedStringException When the entry's submission time can't be read.
     */
    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $entry = EntryOf::form($form);
        if (null === $entry) {
            return HandlerResult::redirect(Urls::handler(FormTabs::ENTRIES, ['form' => $form->id]));
        }

        return HandlerResult::template(
            'forms/entry',
            "Entry #{$entry->id}",
            [
                'form'     => $form->form,
                'entry'    => $entry,
                'answers'  => EntryAnswers::of($form->form, $form->forms->entries->fetchValues($entry->id)),
                'statuses' => EntryStatus::LABELS,
                'changes'  => EntryStatus::CHANGES,
            ],
            FormTabs::of($form->id, FormTabs::ENTRIES),
        );
    }
}
