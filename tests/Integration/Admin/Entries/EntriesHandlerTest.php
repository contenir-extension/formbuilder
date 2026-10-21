<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Entries;

use Contenir\Extension\Formbuilder\Admin\Entries\EntriesHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\EntriesTrait;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;
use function strpos;
use function substr;

/**
 * A form's Entries tab, as the built-in module had it: its entries newest
 * first, found by number, address, status and period, a page at a time.
 */
#[Group('integration')]
#[Group('handler')]
final class EntriesHandlerTest extends TestCase
{
    use EntriesTrait;

    /**
     * @return array<string, array{array<string, scalar>, list<string>, list<string>}>
     */
    public static function filterProvider(): array
    {
        return [
            'a status'          => [['status' => 'spam'], ['#2'], ['#1', '#3']],
            'the last day'      => [['since' => '24h'], ['#1', '#2'], ['#3']],
            'an unknown period' => [['since' => 'ever'], ['#1', '#2', '#3'], []],
            'an entry number'   => [['q' => '3'], ['#3'], ['#1', '#2']],
            'an address'        => [['q' => '198.51'], ['#2'], ['#1', '#3']],
        ];
    }

    #[Test]
    public function countsEachStatusAmongWhatTheSearchFinds(): void
    {
        $form = $this->entries();
        $html = $this->drawn($this->page(EntriesHandler::class, 'GET', ['form' => $form, 'since' => '24h']));

        static::assertSame(
            [true, true, true],
            [
                str_contains($html, '>All statuses (2)</option>'),
                str_contains($html, '>Complete (1)</option>'),
                str_contains($html, '>Spam (1)</option>'),
            ],
        );
    }

    #[Test]
    public function countsThePagesOfWhatTheStatusFinds(): void
    {
        $form    = $this->entries();
        $showing = [];
        foreach (['', 'spam', 'archive'] as $status) {
            $html = $this->drawn($this->page(EntriesHandler::class, 'GET', [
                'form'   => $form,
                'status' => $status,
            ]));
            $showing[$status] = str_contains($html, 'pagination__text')
                ? substr($html, strpos($html, needle: 'Showing'), length: 76)
                : null;
        }

        static::assertSame(
            [
                ''        => 'Showing <strong>1</strong> to <strong>3</strong> of <strong>3</strong></div>',
                'spam'    => 'Showing <strong>1</strong> to <strong>1</strong> of <strong>1</strong></div>',
                'archive' => null,
            ],
            $showing,
        );
    }

    #[Test]
    public function drawsEachEntryWithItsStatusAndMenuCarryingTheToken(): void
    {
        $form = $this->entries();
        $html = $this->drawn($this->page(EntriesHandler::class, 'GET', ['form' => $form]));

        static::assertSame(
            [true, true, true, false, true, true],
            [
                str_contains($html, '<span class="badge badge--destructive-muted">Spam</span>'),
                str_contains(
                    $html,
                    'id="entry-actions" method="post" action="">'
                        . "\n"
                        . '                        <input type="hidden" name="token" value="in-memory">',
                ),
                str_contains(
                    $html,
                    "href=\"/admin/extension/formbuilder/entry-status?form={$form}&amp;entry=2&amp;status=complete\" data-form-submit=\"entry-actions\"",
                ),
                str_contains($html, "entry-status?form={$form}&amp;entry=2&amp;status=spam\""),
                str_contains($html, "entry-delete?form={$form}&amp;entry=2\" data-form-submit=\"entry-actions\""),
                str_contains($html, "entries-export?form={$form}&amp;spam=1\""),
            ],
        );
    }

    #[Test]
    #[DataProvider('filterProvider')]
    public function findsEntriesByWhatTheToolbarAsks(array $query, array $shown, array $hidden): void
    {
        $form  = $this->entries();
        $html  = $this->drawn($this->page(EntriesHandler::class, 'GET', ['form' => $form] + $query));
        $found = [];
        foreach ([...$shown, ...$hidden] as $number) {
            $found[$number] = str_contains($html, "\">{$number}</a>");
        }

        $expected = [];
        foreach ($shown as $number) {
            $expected[$number] = true;
        }
        foreach ($hidden as $number) {
            $expected[$number] = false;
        }

        static::assertSame($expected, $found);
    }

    #[Test]
    public function listsTheNewestFirst(): void
    {
        $form = $this->entries();
        $html = $this->drawn($this->page(EntriesHandler::class, 'GET', ['form' => $form]));

        static::assertTrue(strpos($html, needle: '">#2</a>') < strpos($html, needle: '">#1</a>'));
    }

    #[Test]
    public function pagesFiftyAtATimeKeepingTheFilter(): void
    {
        $form = $this->addForm('Survey', 'survey');
        for ($entry = 0; $entry < 51; ++$entry) {
            $this->addEntry($form);
        }
        $html = $this->drawn($this->page(EntriesHandler::class, 'GET', [
            'form'   => $form,
            'pg'     => 9,
            'status' => 'complete',
        ]));

        static::assertSame(
            [true, true, true, false],
            [
                str_contains($html, 'Showing <strong>51</strong> to <strong>51</strong> of <strong>51</strong>'),
                str_contains($html, "entries?form={$form}&amp;status=complete&amp;pg=1\" aria-label=\"Previous page\""),
                str_contains($html, '">#1</a>'),
                str_contains($html, '">#2</a>'),
            ],
        );
    }

    #[Test]
    public function saysWhenNothingMatches(): void
    {
        $form = $this->entries();
        $html = $this->drawn($this->page(EntriesHandler::class, 'GET', ['form' => $form, 'q' => 'nobody']));

        static::assertSame(
            [true, false],
            [str_contains($html, 'No entries'), str_contains($html, 'pagination__text')],
        );
    }

    #[Test]
    public function showsOnlyTheFormsOwnEntries(): void
    {
        $this->entries();
        $other = $this->addForm('Other', 'other');
        $html  = $this->drawn($this->page(EntriesHandler::class, 'GET', ['form' => $other]));

        static::assertTrue(str_contains($html, 'No entries'));
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * A form with three entries: #1 complete, today; #2 spam from another
     * address, today; #3 complete, a month ago.
     */
    private function entries(): int
    {
        $form = $this->addForm('Contact', 'contact');
        $this->addEntry($form, at: '2026-10-19 10:00');
        $this->addEntry($form, status: 'spam', at: '2026-10-19 11:00', ip: '198.51.100.7');
        $this->addEntry($form, at: '2026-09-19 10:00');

        return $form;
    }
}
