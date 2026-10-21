<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Entries;

use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Formbuilder\Admin\Entries\EntryFilter;
use DateTimeImmutable;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class EntryFilterTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, array{string, string, string, int}}>
     */
    public static function queryProvider(): array
    {
        return [
            'nothing asked'     => [[], ['', 'all', '', 1]],
            'everything asked'  => [
                ['status' => 'spam', 'since' => '7d', 'q' => ' 42 ', 'pg' => '3'],
                ['spam', '7d', '42', 3],
            ],
            'an unknown period' => [['since' => 'ever'], ['', 'all', '', 1]],
            'a page below one'  => [['pg' => '-2'], ['', 'all', '', 1]],
            'values not text'   => [['status' => ['spam'], 'q' => ['42'], 'since' => ['7d']], ['', 'all', '', 1]],
        ];
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function sinceProvider(): array
    {
        return [
            'all time'      => ['all', null],
            'the last day'  => ['24h', '2026-10-19 09:00:00'],
            'the last week' => ['7d', '2026-10-13 09:00:00'],
            'thirty days'   => ['30d', '2026-09-20 09:00:00'],
        ];
    }

    #[Test]
    public function keepsOnlyWhatIsSetForThePageLinks(): void
    {
        $filter = EntryFilter::of((new ServerRequest('GET', '/'))->withQueryParams([
            'status' => 'spam',
            'since'  => 'all',
            'q'      => '',
            'pg'     => '2',
        ]));

        static::assertSame(['status' => 'spam'], $filter->query());
    }

    #[Test]
    public function keepsTheWholeFilterForThePageLinks(): void
    {
        $filter = EntryFilter::of((new ServerRequest('GET', '/'))->withQueryParams([
            'status' => 'spam',
            'since'  => '24h',
            'q'      => 'ada',
        ]));

        static::assertSame(['q' => 'ada', 'status' => 'spam', 'since' => '24h'], $filter->query());
    }

    #[Test]
    #[DataProvider('sinceProvider')]
    public function letsInWhatThePeriodReachesBackToByTheClock(string $since, ?string $expected): void
    {
        $filter = EntryFilter::of((new ServerRequest('GET', '/'))->withQueryParams(['since' => $since]));

        static::assertSame(
            $expected,
            $filter->sinceDate(new FrozenClock(new DateTimeImmutable('2026-10-20 09:00'))),
        );
    }

    /**
     * @param array<string, mixed>              $query
     * @param array{string, string, string, int} $expected
     */
    #[Test]
    #[DataProvider('queryProvider')]
    public function readsWhatTheAddressAsks(array $query, array $expected): void
    {
        $filter = EntryFilter::of((new ServerRequest('GET', '/'))->withQueryParams($query));

        static::assertSame($expected, [$filter->status, $filter->since, $filter->search, $filter->page]);
    }
}
