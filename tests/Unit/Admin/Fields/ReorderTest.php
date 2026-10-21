<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Fields;

use Contenir\Extension\Formbuilder\Admin\Fields\Reorder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ReorderTest extends TestCase
{
    /**
     * @return array<string, array{mixed, array<array-key, mixed>}>
     */
    public static function postedProvider(): array
    {
        return [
            'a list'         => ['[3,1,2]', [3, 1, 2]],
            'rows by id'     => ['{"4":[7,8]}', [4 => [7, 8]]],
            'not JSON'       => ['{rows', []],
            'a JSON number'  => ['3', []],
            'not text'       => [['3'], []],
            'nothing posted' => [null, []],
        ];
    }

    /**
     * @param array<array-key, mixed> $rows
     */
    #[Test]
    #[DataProvider('postedProvider')]
    public function readsTheOrderTheScriptPosted(mixed $posted, array $rows): void
    {
        static::assertSame($rows, Reorder::rows($posted));
    }
}
