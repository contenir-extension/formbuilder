<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Entries;

use Contenir\Extension\Formbuilder\Admin\Entries\EntryPages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class EntryPagesTest extends TestCase
{
    /**
     * @return array<string, array{int, int, array{int, int, int, int, list<int>, int|null, int|null, int}}>
     */
    public static function pageProvider(): array
    {
        return [
            'no entries'                  => [1, 0, [1, 1, 0, 0, [1], null, null, 0]],
            'one page'                    => [1, 50, [1, 1, 1, 50, [1], null, null, 0]],
            'the second of two'           => [2, 51, [2, 2, 51, 51, [1, 2], null, null, 50]],
            'a page past the last'        => [9, 120, [3, 3, 101, 120, [1, 2, 3], null, null, 100]],
            'a page before the first'     => [0, 120, [1, 3, 1, 50, [1, 2, 3], null, null, 0]],
            'the first of many'           => [1, 1000, [1, 20, 1, 50, [1, 2, 3, 4, 5, 6, 7], null, 20, 0]],
            'the middle of many'          => [10, 1000, [10, 20, 451, 500, [7, 8, 9, 10, 11, 12, 13], 1, 20, 450]],
            'near the end of many'        => [19, 1000, [19, 20, 901, 950, [14, 15, 16, 17, 18, 19, 20], 1, null, 900]],
            'the last window, just short' => [16, 1000, [16, 20, 751, 800, [13, 14, 15, 16, 17, 18, 19], 1, 20, 750]],
        ];
    }

    /**
     * @param array{int, int, int, int, list<int>, int|null, int|null, int} $expected
     */
    #[Test]
    #[DataProvider('pageProvider')]
    public function showsThePageAskedWithinThoseThereAre(int $asked, int $count, array $expected): void
    {
        $pages = new EntryPages($asked, $count);

        static::assertSame(
            $expected,
            [
                $pages->page,
                $pages->pages,
                $pages->from,
                $pages->to,
                $pages->range,
                $pages->first,
                $pages->last,
                $pages->offset(),
            ],
        );
    }
}
