<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Persistence;

use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Formbuilder\FormbuilderExtension;
use Contenir\Extension\Formbuilder\Persistence\SiteDatabase;
use DateTimeImmutable;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[Group('unit')]
final class SiteDatabaseTest extends TestCase
{
    #[Test]
    public function refusesAHostOfferingSomethingElse(): void
    {
        $context = $this->host([SiteDatabase::SERVICE => new stdClass()])->context(FormbuilderExtension::HANDLE);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The host offers no site database as "extensions.database.site"');

        SiteDatabase::of($context);
    }

    #[Test]
    public function takesTheSitesDatabaseFromTheHost(): void
    {
        $adapter = $this->createStub(AdapterInterface::class);

        static::assertSame(
            $adapter,
            SiteDatabase::of($this->host([SiteDatabase::SERVICE => $adapter])->context(
                FormbuilderExtension::HANDLE,
            )),
        );
    }

    /**
     * @param array<string, object> $services
     */
    private function host(array $services): InMemoryHost
    {
        $http = new Psr17Factory();
        $host = new InMemoryHost($http, $http, new FrozenClock(new DateTimeImmutable('2026-10-20 09:00')));
        $host->boot(
            [FormbuilderExtension::HANDLE => new FormbuilderExtension()],
            [FormbuilderExtension::HANDLE],
            $services,
        );

        return $host;
    }
}
