<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Fields;

use Contenir\Extension\Formbuilder\Admin\Fields\FormLayout;
use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\GroupDefinition;
use Contenir\FormBuilder\Definition\RowDefinition;
use Contenir\FormBuilder\Definition\SectionDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FormLayoutTest extends TestCase
{
    #[Test]
    public function findsEachPartByIdAndNothingElse(): void
    {
        $layout = $this->layout();

        static::assertSame(
            [1, 2, 4, 6, 'contact', null, null, null, null],
            [
                $layout->section(1)?->id,
                $layout->group(2)?->id,
                $layout->row(4)?->id,
                $layout->field(6)?->id,
                $layout->form->slug,
                $layout->section(2),
                $layout->group(1),
                $layout->row(6),
                $layout->field(4),
            ],
        );
    }

    #[Test]
    public function offersTheOtherNamedFieldsByLabelOrName(): void
    {
        static::assertSame(
            [
                ['name' => 'email', 'label' => 'email', 'type' => 'email'],
                ['name' => 'when', 'label' => 'When', 'type' => 'date'],
            ],
            $this->layout()->siblings(5),
        );
    }

    private function layout(): FormLayout
    {
        $rows = [
            new RowDefinition(3, fields: [
                new FieldDefinition(5, 'text', 'name', 'Name'),
                new FieldDefinition(6, 'email', 'email', ''),
            ]),
            new RowDefinition(4, fields: [
                new FieldDefinition(7, 'date', 'when', 'When'),
                new FieldDefinition(8, 'content', ''),
            ]),
        ];

        return FormLayout::of(new FormDefinition(
            id: 9,
            slug: 'contact',
            title: 'Contact',
            sections: [new SectionDefinition(1, 'main', groups: [new GroupDefinition(2, rows: $rows)])],
        ), 9);
    }
}
