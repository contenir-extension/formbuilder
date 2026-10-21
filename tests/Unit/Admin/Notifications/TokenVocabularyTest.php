<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Notifications;

use Contenir\Extension\Formbuilder\Admin\Notifications\TokenVocabulary;
use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\GroupDefinition;
use Contenir\FormBuilder\Definition\RowDefinition;
use Contenir\FormBuilder\Definition\SectionDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

#[Group('unit')]
final class TokenVocabularyTest extends TestCase
{
    #[Test]
    public function offersALinkToEachUploadUnderFiles(): void
    {
        $tags = TokenVocabulary::of(new FormDefinition(
            id: 1,
            slug: 'contact',
            title: 'Contact',
            sections: [
                new SectionDefinition(1, 'main', groups: [new GroupDefinition(1, rows: [new RowDefinition(1, fields: [
                    new FieldDefinition(1, 'text', 'name', 'Your name'),
                    new FieldDefinition(2, 'file', 'cv', 'Your CV'),
                    new FieldDefinition(3, 'file', 'photo', ''),
                ])])]),
            ],
        ));

        static::assertSame(
            [
                ['Fields', 'Files', 'Form', 'Entry', 'Site', 'Site settings'],
                [
                    ['tag' => '{file:cv}', 'description' => 'Link to Your CV, for 7 days'],
                    ['tag' => '{file:photo}', 'description' => 'Link to the upload, for 7 days'],
                ],
            ],
            [array_column($tags, 'label'), $tags[1]['items']],
        );
    }

    #[Test]
    public function offersEachFieldButHiddenOnesThenTheFormEntrySiteAndSettings(): void
    {
        $tags = TokenVocabulary::of(new FormDefinition(
            id: 1,
            slug: 'contact',
            title: 'Contact',
            sections: [
                new SectionDefinition(1, 'main', groups: [new GroupDefinition(1, rows: [new RowDefinition(1, fields: [
                    new FieldDefinition(1, 'text', 'name', 'Your name'),
                    new FieldDefinition(2, 'hidden', 'source', 'Source'),
                    new FieldDefinition(3, 'email', 'email', ''),
                    new FieldDefinition(4, 'email', 'work', null),
                ])])]),
            ],
        ));

        static::assertSame(
            [
                ['Fields', 'Form', 'Entry', 'Site', 'Site settings'],
                [
                    ['tag' => '{field:name}', 'description' => 'Your name'],
                    ['tag' => '{field:email}', 'description' => 'Submitted value'],
                    ['tag' => '{field:work}', 'description' => 'Submitted value'],
                    ['tag' => '{field:<name>}', 'description' => 'Any other field — replace <name>'],
                ],
                ['{form:title}', '{form:slug}', '{form:description}'],
                ['{entry:id}', '{entry:date}', '{entry:ip}', '{entry:status}', '{entry:fields}'],
                ['{site:admin_url}', '{site:base_url}'],
                [[
                    'tag'         => '{settings:<key>}',
                    'description' => 'Any site setting — replace <key> with its dotted name',
                ]],
            ],
            [
                array_column($tags, 'label'),
                $tags[0]['items'],
                array_column($tags[1]['items'], 'tag'),
                array_column($tags[2]['items'], 'tag'),
                array_column($tags[3]['items'], 'tag'),
                $tags[4]['items'],
            ],
        );
    }
}
