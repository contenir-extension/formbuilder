<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Retention\ExpiredEntries;
use Contenir\FormBuilder\Persistence\PersistenceException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Redact expired entries, under Tools in the Forms menu: a page saying what
 * it does and how many entries it would take now, whose button redacts now
 * what the nightly run would, then goes back to the Forms home saying what
 * it did (FRM-3). It needs Delete, since answers and files are deleted.
 */
final class EntriesRedactHandler implements HandlerInterface
{
    public const string NAME = 'entries-redact';

    /**
     * @throws PersistenceException        When the forms or their entries can't be read or changed.
     * @throws ContainerExceptionInterface When the host can't give the site's database or its storage.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        if ('POST' !== $request->getMethod()) {
            return HandlerResult::template(
                'forms/entries-redact',
                'Redact expired entries',
                ['expired' => ExpiredEntries::count($context)],
                parent: FormsHandler::parent(),
            );
        }

        $report = ExpiredEntries::redact($context);

        return HandlerResult::redirect(Urls::handler(FormsHandler::NAME))
            ->withMessage(0 === $report->failed ? 'success' : 'warning', $report->summary());
    }
}
