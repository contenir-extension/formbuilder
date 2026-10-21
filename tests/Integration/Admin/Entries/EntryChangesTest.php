<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Entries;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Api\Mail\MailerInterface;
use Contenir\Extension\Api\Mail\Message;
use Contenir\Extension\Api\Testing\MemoryMailer;
use Contenir\Extension\Formbuilder\Admin\Entries\EntriesExportHandler;
use Contenir\Extension\Formbuilder\Admin\Entries\EntryDeleteHandler;
use Contenir\Extension\Formbuilder\Admin\Entries\EntryStatusHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\EntriesTrait;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function fopen;
use function rewind;
use function stream_get_contents;

/**
 * Moving, deleting and exporting a form's entries, guarded as the built-in
 * module guarded them.
 */
#[Group('integration')]
#[Group('handler')]
final class EntryChangesTest extends TestCase
{
    use EntriesTrait;

    private MemoryMailer $mailer;

    /**
     * @return array<string, array{string, string, list<string>, int}>
     */
    public static function grantProvider(): array
    {
        return [
            'the list, to a role viewing'          => ['GET', 'entries', ['forms:view'], 200],
            'an entry, to a role viewing'          => ['GET', 'entry', ['forms:view'], 302],
            'a status, by a role viewing'          => ['POST', 'entry-status', ['forms:view'], 403],
            'a status, by a role updating'         => ['POST', 'entry-status', ['forms:update'], 302],
            'deleting, by a role updating'         => ['POST', 'entry-delete', ['forms:update'], 403],
            'deleting, by a role deleting'         => ['POST', 'entry-delete', ['forms:delete'], 302],
            'exporting, by a role viewing'         => ['GET', 'entries-export', ['forms:view'], 403],
            'exporting, by a role allowed exports' => ['GET', 'entries-export', ['forms:export'], 200],
        ];
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function refusedProvider(): array
    {
        return [
            'a GET'                      => ['GET', 'spam', [], true],
            'a status it cannot move to' => ['POST', 'pending', [], true],
            'no status'                  => ['POST', '', [], true],
            'another form'               => ['POST', 'spam', ['other' => '1'], true],
        ];
    }

    #[Test]
    public function deletesAnEntryAndSaysSo(): void
    {
        $form   = $this->addForm('Contact', 'contact');
        $entry  = $this->addEntry($form);
        $result = $this->page(EntryDeleteHandler::class, 'POST', ['form' => $form, 'entry' => $entry]);

        static::assertSame(
            [
                "/admin/extension/formbuilder/entries?form={$form}",
                [['level' => 'success', 'text' => 'Entry deleted.']],
                null,
            ],
            [$result->url, $result->messages, $this->storedEntry($entry)],
        );
    }

    #[Test]
    public function exportsSpamWhenTheLinkAsks(): void
    {
        $csv = $this->written($this->page(EntriesExportHandler::class, 'GET', [
            'form' => $this->exported(),
            'spam' => '1',
        ]));

        static::assertStringContainsString('2,"2026-10-19 11:00:00",spam,Bot', $csv);
    }

    #[Test]
    public function exportsTheEntriesWithoutSpamAsCsv(): void
    {
        $form = $this->exported();
        $file = $this->page(EntriesExportHandler::class, 'GET', ['form' => $form])->download;

        static::assertSame(
            [
                'contact-entries.csv',
                'text/csv; charset=UTF-8',
                "form_entry_id,submitted_at,status,name,email,message\n1,\"2026-10-19 10:00:00\",complete,'=SUM(A1),,\n",
            ],
            [$file?->filename, $file?->mediaType, $this->written($this->page(EntriesExportHandler::class, 'GET', [
                'form' => $form,
            ]))],
        );
    }

    #[Test]
    #[DataProvider('grantProvider')]
    public function guardsEachScreenAsTheBuiltInModuleDid(
        string $method,
        string $handler,
        array $grants,
        int $status,
    ): void {
        $ids      = $this->layOut();
        $response = $this->host->handle(
            (new Psr17Factory())->createServerRequest(
                $method,
                "/admin/extension/formbuilder/{$handler}?form={$ids['form']}",
            ),
            $grants,
        );

        static::assertSame($status, $response->getStatusCode());
    }

    #[Test]
    #[DataProvider('refusedProvider')]
    public function leavesAnEntryItIsNotAskedRightlyToMove(
        string $method,
        string $status,
        array $other,
        bool $kept,
    ): void {
        $form   = $this->addForm('Contact', 'contact');
        $entry  = $this->addEntry($form);
        $target = [] === $other ? $form : $this->addForm('Other', 'other');
        $result = $this->page(EntryStatusHandler::class, $method, [
            'form'   => $target,
            'entry'  => $entry,
            'status' => $status,
        ]);

        static::assertSame(
            [ResultKind::Redirect, [], 'complete'],
            [$result->kind, $result->messages, $this->storedEntry($entry)['status'] ?? null],
        );
    }

    #[Test]
    public function movesAnEntryAndSaysSo(): void
    {
        $form   = $this->addForm('Contact', 'contact');
        $entry  = $this->addEntry($form);
        $result = $this->page(EntryStatusHandler::class, 'POST', [
            'form'   => $form,
            'entry'  => $entry,
            'status' => 'archive',
        ]);

        static::assertSame(
            [
                "/admin/extension/formbuilder/entries?form={$form}",
                [['level' => 'success', 'text' => 'Entry updated.']],
                'archive',
            ],
            [$result->url, $result->messages, $this->storedEntry($entry)['status'] ?? null],
        );
    }

    #[Test]
    public function readsTheStatusPostedWhenTheAddressHasNone(): void
    {
        $form  = $this->addForm('Contact', 'contact');
        $entry = $this->addEntry($form);
        $this->page(EntryStatusHandler::class, 'POST', ['form' => $form, 'entry' => $entry], ['status' => 'spam']);

        static::assertSame('spam', $this->storedEntry($entry)['status'] ?? null);
    }

    #[Test]
    public function sendsOnSpamMarkedCompleteWithItsNotifications(): void
    {
        $this->mailer = new MemoryMailer();
        $this->setUpHost([MailerInterface::class => $this->mailer]);
        $ids = $this->layOut();
        $this->repository()->createNotification($ids['form'], [
            'name'          => 'Team',
            'to_address'    => 'team@example.com',
            'subject'       => 'New enquiry from {field:name}',
            'body_template' => '<p>{field:message} at {site:base_url}, seen in {site:admin_url}</p>',
        ]);
        $entry  = $this->addEntry($ids['form'], ['name' => 'Ada', 'message' => 'Hello'], 'spam');
        $result = (new EntryStatusHandler())->handle(
            (new ServerRequest('POST', 'https://www.example.com/admin/extension/formbuilder/entry-status'))
                ->withQueryParams(['form' => $ids['form'], 'entry' => $entry, 'status' => 'complete']),
            $this->context(),
        );

        static::assertSame(
            [
                [['level' => 'success', 'text' => 'Entry marked complete, and its notifications and webhooks sent.']],
                ['New enquiry from Ada'],
                ['<p>Hello at https://www.example.com, seen in /admin</p>'],
            ],
            [
                $result->messages,
                array_map(static fn(Message $message): string => $message->subject, $this->mailer->sent()),
                array_map(static fn(Message $message): string => $message->html, $this->mailer->sent()),
            ],
        );
    }

    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * A form laid out for the fields editor with a complete entry whose name
     * a spreadsheet would read as a formula, and a spam one.
     */
    private function exported(): int
    {
        $form = $this->layOut()['form'];
        $this->addEntry($form, ['name' => '=SUM(A1)']);
        $this->addEntry($form, ['name' => 'Bot'], 'spam', '2026-10-19 11:00');

        return $form;
    }

    private function written(HandlerResult $result): string
    {
        $file   = $result->download ?? throw new LogicException('Not a download');
        $stream = fopen('php://memory', mode: 'w+b');
        if (false === $stream) {
            throw new LogicException('PHP could not open a memory stream');
        }

        ($file->writer)($stream);
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
