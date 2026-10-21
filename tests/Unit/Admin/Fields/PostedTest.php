<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Fields;

use Contenir\Extension\Formbuilder\Admin\Fields\Posted;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PostedTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string, ?string, int, bool}>
     */
    public static function valueProvider(): array
    {
        return [
            'text with spaces' => ['  Ada ', 'Ada', 'Ada', 0, true],
            'only spaces'      => ['   ', '', null, 0, true],
            'a number'         => ['12', '12', '12', 12, true],
            'zero'             => ['0', '0', '0', 0, false],
            'empty'            => ['', '', null, 0, false],
            'a list'           => [['a'], '', null, 0, false],
            'nothing'          => [null, '', null, 0, false],
        ];
    }

    #[Test]
    public function keepsTheWholeNumbersOfAListInOrder(): void
    {
        static::assertSame([[3, 1, 2], []], [Posted::numbers(['3', 'x', 1, null, '2']), Posted::numbers('3')]);
    }

    #[Test]
    public function listsAValueOrKeepsAList(): void
    {
        static::assertSame([['a'], ['a', 'b'], [null]], [
            Posted::items('a'),
            Posted::items(['a', 'b']),
            Posted::items(null),
        ]);
    }

    #[Test]
    #[DataProvider('valueProvider')]
    public function readsAValueAsTextNumberAndTick(
        mixed $value,
        string $text,
        ?string $optional,
        int $number,
        bool $ticked,
    ): void {
        static::assertSame(
            [$text, $optional, $number, $ticked],
            [Posted::text($value), Posted::optional($value), Posted::number($value), Posted::ticked($value)],
        );
    }
}
