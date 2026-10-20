<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Persistence;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\FormBuilder\Loader\PhpDbFormLoader;
use Contenir\FormBuilder\Repository\PhpDbFormRepository;
use LogicException;
use Psr\Container\ContainerExceptionInterface;

/**
 * The form builder's persistence over the site's database, as the add-on's
 * handlers use it: the loader that reads whole forms and the repository that
 * writes them, its times from the host's clock.
 */
final readonly class Forms
{
    private function __construct(
        public PhpDbFormLoader $loader,
        public PhpDbFormRepository $repository,
    ) {}

    /**
     * @throws LogicException              When the host offers no site database.
     * @throws ContainerExceptionInterface When the host can't make it.
     */
    public static function of(ExtensionContextInterface $context): self
    {
        $database = SiteDatabase::of($context);

        return new self(new PhpDbFormLoader($database), new PhpDbFormRepository($database, $context->clock()));
    }
}
