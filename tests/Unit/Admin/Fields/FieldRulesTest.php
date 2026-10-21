<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Fields;

use Contenir\Extension\Formbuilder\Admin\Fields\FieldRules;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FieldRulesTest extends TestCase
{
    #[Test]
    public function keepsTheRulesTickedInOrderWithTheirOptionsAndMessages(): void
    {
        static::assertSame(
            [
                [
                    'type'    => 'between',
                    'options' => ['min' => '1', 'max' => 0, 'list' => ['x']],
                    'message' => 'Out of range',
                ],
                ['type' => 'email', 'options' => [], 'message' => null],
            ],
            FieldRules::of([
                ['present' => '0'],
                [
                    'type'    => 'between',
                    'options' => ['min' => '1', 'max' => 0, 'step' => ' ', 'list' => ['x']],
                    'message' => ' Out of range ',
                ],
                'not a rule',
                ['type' => ' ', 'options' => ['min' => '1']],
                ['type' => 'email', 'options' => 'none'],
            ]),
        );
    }

    #[Test]
    public function readsNoRulesFromAnythingElse(): void
    {
        static::assertSame([], FieldRules::of('email'));
    }
}
