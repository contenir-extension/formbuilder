<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Notifications;

use Contenir\Extension\Formbuilder\Admin\Notifications\NotificationPreview;
use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\GroupDefinition;
use Contenir\FormBuilder\Definition\RowDefinition;
use Contenir\FormBuilder\Definition\SectionDefinition;
use Contenir\FormBuilder\Service\TokenReplacer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;

#[Group('unit')]
final class NotificationPreviewTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function sampleProvider(): array
    {
        return [
            'an email'        => ['email', 'alex@example.com'],
            'a phone number'  => ['tel', '+61 4 0000 0000'],
            'a URL'           => ['url', 'https://example.com'],
            'a number'        => ['number', '42'],
            'a date'          => ['date', '2026-10-21'],
            'a date and time' => ['datetime', '2026-10-21 14:05'],
            'a time'          => ['time', '14:05'],
            'a checkbox'      => ['checkbox', '1'],
            'a long answer'   => ['textarea', "Sample multi-line content.\nLine two for visual rhythm."],
            'a file'          => ['file', 'uploads/sample.pdf'],
            'a hidden value'  => ['hidden', 'hidden-value'],
            'a choice'        => ['select', 'Option A'],
            'a radio choice'  => ['radio', 'Option A'],
            'several choices' => ['multiselect', 'Option A'],
            'anything else'   => ['text', 'Sample answer'],
        ];
    }

    #[Test]
    #[DataProvider('sampleProvider')]
    public function fillsEachFieldWithASampleOfItsType(string $type, string $sample): void
    {
        static::assertSame(
            "<span class=\"token-replaced\" title=\"{field:answer}\">{$sample}</span>",
            $this->preview('{field:answer}', $type),
        );
    }

    #[Test]
    public function fillsInTheEntryAndSiteAndLeavesUnknownTagsAsTheyAre(): void
    {
        static::assertSame(
            '<p>#<span class="token-replaced" title="{entry:id}">0</span> from '
                . '<span class="token-replaced" title="{entry:ip}">127.0.0.1</span> at '
                . '<span class="token-replaced" title="{entry:date}">2026-10-21 14:05:00</span>, {nope:x}, '
                . '<span class="token-replaced" title="{site:admin_url}">/admin</span></p>',
            $this->preview('<p>#{entry:id} from {entry:ip} at {entry:date}, {nope:x}, {site:admin_url}</p>', 'text'),
        );
    }

    #[Test]
    public function leavesAMarkupFillingAndATagInsideAnElementUnmarked(): void
    {
        static::assertSame(
            ['<a href="mailto:alex@example.com">', '<p>after</p>', true],
            [
                $this->preview('<a href="mailto:{field:answer}">', 'email'),
                $this->preview('<p>after</p>', 'email'),
                str_contains($this->preview('{entry:fields}', 'email'), '<table'),
            ],
        );
    }

    #[Test]
    public function leavesEveryTagInsideAnElementUnmarked(): void
    {
        static::assertSame(
            '<a title="42" href="Contact">',
            $this->preview('<a title="{field:answer}" href="{form:title}">', 'number'),
        );
    }

    #[Test]
    public function marksATagAfterAClosedElement(): void
    {
        static::assertSame(
            '<b>x</b> <span class="token-replaced" title="{field:answer}">42</span> <i>',
            $this->preview('<b>x</b> {field:answer} <i>', 'number'),
        );
    }

    #[Test]
    public function saysAnEmptyBodyIsEmpty(): void
    {
        static::assertSame('<p class="preview-empty">Body template is empty.</p>', $this->preview('', 'text'));
    }

    private function preview(string $template, string $type): string
    {
        $form = new FormDefinition(
            id: 1,
            slug: 'contact',
            title: 'Contact',
            sections: [
                new SectionDefinition(1, 'main', groups: [new GroupDefinition(1, rows: [
                    new RowDefinition(1, fields: [new FieldDefinition(1, $type, 'answer', 'Answer')]),
                ])]),
            ],
        );

        return (new NotificationPreview(
            new TokenReplacer(['admin_url' => '/admin']),
            new DateTimeImmutable('2026-10-21 14:05:00'),
        ))->body($template, $form);
    }
}
