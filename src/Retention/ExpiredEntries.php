<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Retention;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Formbuilder\Persistence\Forms;
use Contenir\Extension\Formbuilder\Uploads\UploadStorage;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Contenir\FormBuilder\Redaction\ExpiredEntryRedactor;
use Contenir\FormBuilder\Redaction\NoFileStorage;
use Contenir\FormBuilder\Redaction\RedactionReport;
use LogicException;
use Psr\Container\ContainerExceptionInterface;

use function count;

/**
 * Redacts the entries each form with a retention period has kept longer than
 * that, by the host's clock: their answers and uploaded files are deleted
 * and their address cleared. Files are deleted from the storage the host
 * lends; without it, an entry with files is kept and logged, and tried
 * again next time.
 */
final class ExpiredEntries
{
    /**
     * How many entries a redaction would take now, across every form with a
     * retention period, for the page that asks first.
     *
     * @throws PersistenceException        When the forms or their entries can't be read.
     * @throws ContainerExceptionInterface When the host can't give the site's database.
     * @throws LogicException              When the host offers no site database.
     */
    public static function count(ExtensionContextInterface $context): int
    {
        $forms = Forms::of($context);
        $count = 0;
        foreach ($forms->loader->loadAll() as $form) {
            if (null === $form->id || null === $form->retentionDays || $form->retentionDays < 1) {
                continue;
            }

            $count += count($forms->entries->findExpired($form->id, $form->retentionDays));
        }

        return $count;
    }

    /**
     * @throws PersistenceException        When the forms or their entries can't be read or changed.
     * @throws ContainerExceptionInterface When the host can't give the site's database or its storage.
     * @throws LogicException              When the host offers no site database.
     */
    public static function redact(ExtensionContextInterface $context): RedactionReport
    {
        $forms = Forms::of($context);

        return (new ExpiredEntryRedactor(
            $forms->loader,
            $forms->entries,
            UploadStorage::of($context) ?? new NoFileStorage($context->logger()),
        ))->redactExpired();
    }
}
