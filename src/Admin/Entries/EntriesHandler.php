<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\FormBuilder\Persistence\PersistenceException;
use DateMalformedStringException;
use Override;

use function array_map;
use function array_sum;

/**
 * A form's Entries tab: its entries newest first, a page at a time, found by
 * number or address, status and period, each with its status and a menu to
 * view it, move it to another status or delete it, and Export CSV, as the
 * built-in module had them. Drawn from a template, since a described table
 * can't carry a row's links and menu.
 */
final class EntriesHandler extends AbstractFormHandler
{
    public const string NAME = FormTabs::ENTRIES;

    /**
     * @throws PersistenceException         When the entries can't be read or changed.
     * @throws DateMalformedStringException When an entry's submission time can't be read.
     */
    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $filter   = EntryFilter::of($form->request);
        $since    = $filter->sinceDate($form->context->clock());
        $search   = '' === $filter->search ? null : $filter->search;
        $repo     = $form->forms->entries;
        $counts   = $repo->countByStatus($form->id, $since, $search);
        $statuses = '' === $filter->status ? null : [$filter->status];
        $pages    = new EntryPages(
            $filter->page,
            null === $statuses ? array_sum($counts) : $counts[$filter->status] ?? 0,
        );

        return HandlerResult::template(
            'forms/entries',
            $form->form->title,
            [
                'form'     => $form->form,
                'filter'   => $filter,
                'counts'   => $counts,
                'pages'    => $pages,
                'statuses' => EntryStatus::LABELS,
                'changes'  => EntryStatus::CHANGES,
                'entries'  => array_map(EntryRow::of(...), $repo->listEntries(
                    $form->id,
                    $statuses,
                    EntryPages::PER_PAGE,
                    $pages->offset(),
                    $since,
                    $search,
                )),
            ],
            FormTabs::of($form->id, self::NAME),
        );
    }
}
