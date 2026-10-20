<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Persistence;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use LogicException;
use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerExceptionInterface;

use function sprintf;

/**
 * The site's database, where the form_* tables live (P10), as the host
 * offers it to add-ons: a php-db adapter over the site's own connection, so
 * public sites and the CMS read the same rows.
 */
final class SiteDatabase
{
    /**
     * The service the CMS gives add-ons the site's database under.
     */
    public const string SERVICE = 'extensions.database.site';

    /**
     * @throws LogicException              When the host offers no such database.
     * @throws ContainerExceptionInterface When the host can't make it.
     *
     * @mago-expect analysis:mixed-assignment A container's services are untyped; the adapter is checked before use.
     */
    public static function of(ExtensionContextInterface $context): AdapterInterface
    {
        $adapter = $context->services()->get(self::SERVICE);
        if (! $adapter instanceof AdapterInterface) {
            throw new LogicException(sprintf('The host offers no site database as "%s"', self::SERVICE));
        }

        return $adapter;
    }
}
