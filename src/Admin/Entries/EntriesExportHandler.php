<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\FormBuilder\Export\EntryCsvWriter;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Contenir\FormBuilder\Repository\EntryRepositoryInterface;
use Override;

/**
 * A form's entries as CSV, newest first, written as they are read so a form
 * with many entries is never held whole. Spam is left out unless the link
 * asks for it with spam=1. A cell a spreadsheet would read as a formula is
 * written so it can't run.
 */
final class EntriesExportHandler extends AbstractFormHandler
{
    public const string NAME = 'entries-export';

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $writer     = new EntryCsvWriter($form->forms->entries);
        $definition = $form->form;
        $excluded   = '1' === ($form->request->getQueryParams()['spam'] ?? null)
            ? []
            : [EntryRepositoryInterface::STATUS_SPAM];

        return HandlerResult::download(
            "{$definition->slug}-entries.csv",
            'text/csv; charset=UTF-8',
            /**
             * @throws PersistenceException When the entries can't be read.
             */
            static function ($stream) use ($writer, $definition, $excluded): void {
                $writer->write($stream, $definition, $excluded);
            },
        );
    }
}
