<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Entries;

use Contenir\Extension\Api\Handler\ParentPage;
use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Formbuilder\Admin\Entries\EntryHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\EntriesTrait;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;

/**
 * One entry under its form's Entries tab, its answers shown as a person
 * reads them, as the built-in module had it.
 */
#[Group('integration')]
#[Group('handler')]
final class EntryHandlerTest extends TestCase
{
    use EntriesTrait;

    /**
     * @return array<string, array{string}>
     */
    public static function answerProvider(): array
    {
        return [
            'the submission'             => ['<dd class="datalist__value"><code>192.0.2.10</code></dd>'],
            'its status'                 => ['<span class="badge badge--warning-muted">Pending</span>'],
            'the group as a card'        => ['<h2 class="card__title">Details</h2>'],
            'the section description'    => ['<p class="card__description">Tell us who you are</p>'],
            'text'                       => [
                '<dt class="datalist__label">Name</dt>'
                    . "\n"
                    . '                                <dd class="datalist__value">Ada &lt;Lovelace&gt;</dd>',
            ],
            'a field not answered'       => [
                '<dt class="datalist__label">Email</dt>'
                    . "\n"
                    . '                                <dd class="datalist__value"><span class="text--muted">Not set</span></dd>',
            ],
            'a long answer, wide'        => ['<div class="datalist__item datalist__item--wide">'],
            'a long answer, its lines'   => ['Line one<br />' . "\n" . 'Line two'],
            'a field without a label'    => ['<dt class="datalist__label">message</dt>'],
            'a choice by its label'      => ['<dd class="datalist__value">Tea &amp; cake</dd>'],
            'a choice no longer offered' => ['<dd class="datalist__value">coffee</dd>'],
            'many choices, a line each'  => ['<dd class="datalist__value">Tea &amp; cake<br>scones</dd>'],
            'a checkbox ticked'          => ['<dd class="datalist__value">Yes</dd>'],
            'a checkbox left'            => ['<dd class="datalist__value">No</dd>'],
            'a web address'              => [
                '<a href="https://example.com/?a=1&amp;b=2" target="_blank" rel="noopener">',
            ],
            'an email address'           => ['<a href="mailto:grace@example.com">grace@example.com</a>'],
            'a file as its download'     => [
                '<dd class="datalist__value"><a href="/admin/extension/formbuilder/entry-file?form=1&amp;entry=1&amp;field=cv">cv.pdf</a></dd>',
            ],
            'a value the form lost'      => [
                '<dt class="datalist__label">old_field</dt>'
                    . "\n"
                    . '                                <dd class="datalist__value">a, b</dd>',
            ],
            'Delete, asking first'       => [
                'data-confirm="Delete Entry #1? Its uploaded files are deleted too. This cannot be undone."',
            ],
            'a status change'            => [
                'entry-status?form=1&amp;entry=1&amp;status=spam" data-form-submit="entry-status"',
            ],
        ];
    }

    #[Test]
    #[DataProvider('answerProvider')]
    public function drawsTheEntryAsAPersonReadsIt(string $shown): void
    {
        static::assertStringContainsString($shown, $this->drawn($this->page(EntryHandler::class, 'GET', [
            'form'  => $this->form(),
            'entry' => 1,
        ])));
    }

    #[Test]
    public function leavesHiddenAndContentFieldsOut(): void
    {
        $html = $this->drawn($this->page(EntryHandler::class, 'GET', ['form' => $this->form(), 'entry' => 1]));

        static::assertSame(
            [false, false],
            [str_contains($html, 'Campaign'), str_contains($html, 'Intro')],
        );
    }

    #[Test]
    public function offersOnlyTheStatusesTheEntryIsNotIn(): void
    {
        $html = $this->drawn($this->page(EntryHandler::class, 'GET', ['form' => $this->form(), 'entry' => 2]));

        static::assertSame(
            [false, true],
            [str_contains($html, 'status=complete"'), str_contains($html, 'status=archive"')],
        );
    }

    #[Test]
    public function sendsAMissingEntryBackToTheList(): void
    {
        $result = $this->page(EntryHandler::class, 'GET', ['form' => $this->form(), 'entry' => 99]);

        static::assertSame(ResultKind::Redirect, $result->kind);
    }

    #[Test]
    public function sendsAnEntryOfAnotherFormBackToTheList(): void
    {
        $this->form();
        $other  = $this->addForm('Other', 'other');
        $result = $this->page(EntryHandler::class, 'GET', ['form' => $other, 'entry' => 1]);

        static::assertSame(
            [ResultKind::Redirect, "/admin/extension/formbuilder/entries?form={$other}"],
            [$result->kind, $result->url],
        );
    }

    #[Test]
    public function showsTheEntryUnderItsForm(): void
    {
        $form = $this->form();

        static::assertEquals(
            new ParentPage('Contact', 'entries', ['form' => $form]),
            $this->page(EntryHandler::class, 'GET', ['form' => $form, 'entry' => 1])->template?->parent,
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * A form laid out for the fields editor with a field of each kind the
     * entry shows its own way, and two entries: #1 pending with an answer
     * for each, and #2 complete.
     */
    private function form(): int
    {
        $ids        = $this->layOut();
        $repository = $this->repository();
        $repository->updateSection($ids['section'], ['description' => 'Tell us who you are']);
        $row     = $ids['rows'][1];
        $choices = ['choices' => [['value' => 'tea', 'label' => 'Tea & cake'], ['value' => 'scones']]];
        $repository->createField($row, ['type' => 'select', 'name' => 'snack', 'options' => $choices]);
        $repository->createField($row, ['type' => 'radio', 'name' => 'drink', 'options' => $choices]);
        $repository->createField($row, ['type' => 'multicheckbox', 'name' => 'extras', 'options' => $choices]);
        $repository->createField($row, ['type' => 'checkbox', 'name' => 'agree']);
        $repository->createField($row, ['type' => 'checkbox', 'name' => 'news']);
        $repository->createField($row, ['type' => 'url', 'name' => 'site']);
        $repository->createField($row, ['type' => 'email', 'name' => 'work']);
        $repository->createField($row, ['type' => 'file', 'name' => 'cv']);
        $repository->createField($row, ['type' => 'hidden', 'name' => 'campaign', 'label' => 'Campaign']);
        $repository->createField($row, ['type' => 'content', 'name' => 'intro', 'label' => 'Intro']);
        $this->addEntry(
            $ids['form'],
            [
                'name'      => 'Ada <Lovelace>',
                'message'   => "Line one\nLine two",
                'snack'     => 'tea',
                'drink'     => 'coffee',
                'extras'    => ['tea', 'scones'],
                'agree'     => '1',
                'news'      => '0',
                'site'      => 'https://example.com/?a=1&b=2',
                'work'      => 'grace@example.com',
                'cv'        => 'forms/contact/cv.pdf',
                'campaign'  => 'autumn',
                'old_field' => ['a', 'b'],
            ],
            'pending',
        );
        $this->addEntry($ids['form']);

        return $ids['form'];
    }
}
