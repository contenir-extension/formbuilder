<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Uploads;

use Contenir\Extension\Formbuilder\Uploads\EntryUploads;
use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\GroupDefinition;
use Contenir\FormBuilder\Definition\RowDefinition;
use Contenir\FormBuilder\Definition\SectionDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class EntryUploadsTest extends TestCase
{
    private static function form(): FormDefinition
    {
        return new FormDefinition(
            id: 9,
            slug: 'contact',
            title: 'Contact',
            sections: [new SectionDefinition(1, 'main', groups: [new GroupDefinition(2, rows: [
                new RowDefinition(3, fields: [
                    new FieldDefinition(4, 'text', 'name'),
                    new FieldDefinition(5, 'file', 'cv'),
                    new FieldDefinition(6, 'file', 'photo'),
                ]),
            ])])],
        );
    }

    #[Test]
    public function findsTheFileEachUploadFieldHolds(): void
    {
        static::assertSame(
            ['cv' => 'forms/contact/cv.pdf', 'photo' => 'forms/contact/photo.png'],
            EntryUploads::of(self::form(), [
                ['field_name' => 'name', 'value_text' => 'forms/contact/name.pdf'],
                ['field_name' => 'cv', 'value_text' => ' /forms/contact/cv.pdf'],
                ['field_name' => 'photo', 'value_text' => ''],
                ['field_name' => 'photo', 'value_text' => 'forms/contact/photo.png'],
                ['field_name' => 'gone', 'value_text' => 'forms/contact/gone.pdf'],
                ['field_name' => null, 'value_text' => 'forms/contact/none.pdf'],
                ['field_name' => 'photo', 'value_text' => null, 'value_json' => '["a.pdf"]'],
            ]),
        );
    }
}
