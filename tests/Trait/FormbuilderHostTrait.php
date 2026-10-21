<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Trait;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Formbuilder\FormbuilderExtension;
use Contenir\Extension\Formbuilder\Persistence\SiteDatabase;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * The add-on loaded on the API's in-memory host, over a freshly migrated
 * site database. Call setUpHost() from setUp().
 */
trait FormbuilderHostTrait
{
    use FormsDatabaseTrait;

    private InMemoryHost $host;

    private function context(): ExtensionContextInterface
    {
        return $this->host->context(FormbuilderExtension::HANDLE);
    }

    /**
     * @param array<string, object> $services What else the host offers the add-on, by name
     */
    private function setUpHost(array $services = []): void
    {
        $this->setUpDatabase();
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost($http, $http, new FrozenClock(new DateTimeImmutable('2026-10-20 09:00')));
        $this->host->boot(
            [FormbuilderExtension::HANDLE => new FormbuilderExtension()],
            [FormbuilderExtension::HANDLE],
            [SiteDatabase::SERVICE => $this->database] + $services,
        );
    }
}
