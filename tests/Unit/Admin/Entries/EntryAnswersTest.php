<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Entries;

use Contenir\Extension\Formbuilder\Admin\Entries\EntryAnswers;
use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\GroupDefinition;
use Contenir\FormBuilder\Definition\RowDefinition;
use Contenir\FormBuilder\Definition\SectionDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class EntryAnswersTest extends TestCase
{
    private static function form(): FormDefinition
    {
        return new FormDefinition(
            id: 9,
            slug: 'contact',
            title: 'Contact',
            sections: [new SectionDefinition(1, 'main', groups: [new GroupDefinition(2, rows: [
                new RowDefinition(3, fields: [new FieldDefinition(4, 'text', 'name')]),
            ])])],
        );
    }

    #[Test]
    public function keepsValuesUnderNamesTheFormLostApart(): void
    {
        $answers = EntryAnswers::of(self::form(), [
            ['field_name' => 'name', 'value_text' => 'Ada'],
            ['field_name' => 'old', 'value_text' => 'kept'],
        ]);

        static::assertSame(
            [['name' => 'Ada', 'old' => 'kept'], ['old' => 'kept']],
            [$answers->byName, $answers->orphans],
        );
    }

    #[Test]
    public function readsAListFromItsJsonAndTextOtherwise(): void
    {
        $answers = EntryAnswers::of(self::form(), [
            ['field_name' => 'topics', 'value_text' => null, 'value_json' => '["tea",2,null,{"a":1}]'],
            ['field_name' => 'map', 'value_text' => null, 'value_json' => '{"a":"b"}'],
            ['field_name' => 'broken', 'value_text' => 'fallback', 'value_json' => '[oops'],
            ['field_name' => 'count', 'value_text' => 3],
            ['field_name' => 'nothing', 'value_text' => null, 'value_json' => null],
        ]);

        static::assertSame(
            ['topics' => ['tea', '2', '', ''], 'map' => '', 'broken' => 'fallback', 'count' => '3', 'nothing' => ''],
            $answers->byName,
        );
    }

    #[Test]
    public function skipsAValueWithNoName(): void
    {
        $answers = EntryAnswers::of(self::form(), [
            ['field_name' => '', 'value_text' => 'lost'],
            ['value_text' => 'lost'],
        ]);

        static::assertSame([], $answers->byName);
    }
}
