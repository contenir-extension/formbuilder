<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Uploads;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\WebhookInterface;
use Contenir\Extension\Api\Handler\WebhookResponse;
use Contenir\Extension\Formbuilder\Admin\Entries\EntryRow;
use Contenir\Extension\Formbuilder\Persistence\Forms;
use Contenir\FormBuilder\Persistence\PersistenceException;
use DateMalformedStringException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use RuntimeException;

use function is_numeric;
use function is_string;

/**
 * Sends the file an entry's upload field holds to anyone with a link from a
 * notification email, at /api/extension/formbuilder/file, until the link
 * expires. A link that is unsigned, altered or expired is refused before the
 * file is looked for; a file that has gone since is answered Not Found.
 */
final class FileWebhook implements WebhookInterface
{
    public const string NAME = 'file';

    /**
     * @throws PersistenceException         When the entry or its form can't be read.
     * @throws DateMalformedStringException When the entry's submission time can't be read.
     * @throws ContainerExceptionInterface  When the host can't give the site's database or its storage.
     * @throws LogicException               When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): WebhookResponse
    {
        $query  = $request->getQueryParams();
        $id     = is_numeric($query['entry'] ?? null) ? (int) $query['entry'] : 0;
        $field  = is_string($query['field'] ?? null) ? $query['field'] : '';
        $forms  = Forms::of($context);
        $stored = $forms->entries->findEntry($id);
        $form   = null === $stored ? null : $forms->loader->loadById(EntryRow::of($stored)->formId);
        $file   = null === $form ? null : EntryFile::of($forms, UploadStorage::of($context), $form, $id, $field);

        return null === $file
            ? WebhookResponse::text('This file is no longer available.', 404)
            : WebhookResponse::download($file->name, 'application/octet-stream', $file->writer);
    }

    /**
     * @throws ContainerExceptionInterface When the host can't give the site's database.
     * @throws LogicException              When the host offers no site database.
     * @throws RandomException             When no key can be made.
     * @throws RuntimeException            When the key can be neither read nor kept.
     */
    #[Override]
    public function verify(ServerRequestInterface $request, ExtensionContextInterface $context): bool
    {
        return FileLinks::of($context)->allows($request->getQueryParams());
    }
}
