<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Retention;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Schedule\ScheduledTaskInterface;
use Contenir\FormBuilder\Persistence\PersistenceException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;

/**
 * Redacts expired entries every night, as the built-in module's cron job
 * did, and logs what it did.
 */
final class RedactExpiredTask implements ScheduledTaskInterface
{
    public const string NAME = 'redact-expired';

    /**
     * Every night at 03:00, by the site's clock.
     */
    public const string AT = '0 3 * * *';

    /**
     * @throws PersistenceException        When the forms or their entries can't be read or changed.
     * @throws ContainerExceptionInterface When the host can't give the site's database or its storage.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function run(ExtensionContextInterface $context): void
    {
        $context->logger()->info(ExpiredEntries::redact($context)->summary());
    }
}
