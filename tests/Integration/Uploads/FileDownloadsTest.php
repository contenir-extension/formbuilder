<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Uploads;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Api\Mail\MailerInterface;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\MemoryMailer;
use Contenir\Extension\Formbuilder\Admin\Entries\EntryDeleteHandler;
use Contenir\Extension\Formbuilder\Admin\Entries\EntryFileHandler;
use Contenir\Extension\Formbuilder\Admin\Entries\EntryStatusHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\UploadsTrait;
use Contenir\Extension\Formbuilder\Uploads\FileLinks;
use Contenir\Extension\Formbuilder\Uploads\SigningKey;
use Contenir\Extension\Formbuilder\Uploads\UploadStorage;
use Contenir\Storage\Exception\WriteException;
use Contenir\Storage\StorageInterface;
use Contenir\Storage\StorageManager;
use DateTimeImmutable;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function fopen;
use function html_entity_decode;
use function preg_match;
use function rewind;
use function stream_get_contents;

/**
 * An entry's uploads: downloaded by someone viewing the entry, sent to anyone
 * holding a signed link from a notification email until it expires, and
 * deleted with the entry (FRM-2).
 */
#[Group('integration')]
#[Group('handler')]
final class FileDownloadsTest extends TestCase
{
    use UploadsTrait;

    #[Test]
    public function deletesAnEntrysFilesWithIt(): void
    {
        $form  = $this->layOutWithUpload()['form'];
        $entry = $this->addEntry($form, ['cv' => 'forms/contact/cv.pdf']);
        $this->private->putFile('forms/contact/cv.pdf', 'CV');
        $result = $this->page(EntryDeleteHandler::class, 'POST', ['form' => $form, 'entry' => $entry]);

        static::assertSame(
            [[['level' => 'success', 'text' => 'Entry deleted.']], false, null],
            [$result->messages, $this->private->exists('forms/contact/cv.pdf'), $this->storedEntry($entry)],
        );
    }

    #[Test]
    public function downloadsAFileFromPublicStorageUntilItIsMoved(): void
    {
        $form  = $this->layOutWithUpload()['form'];
        $entry = $this->addEntry($form, ['cv' => 'forms/contact/cv.pdf']);
        $this->public->putFile('forms/contact/cv.pdf', 'Public CV');

        static::assertSame('Public CV', $this->written($this->page(EntryFileHandler::class, 'GET', [
            'form'  => $form,
            'entry' => $entry,
            'field' => 'cv',
        ])));
    }

    #[Test]
    public function downloadsAnEntrysFileFromPrivateStorage(): void
    {
        $form  = $this->layOutWithUpload()['form'];
        $entry = $this->addEntry($form, ['cv' => 'forms/contact/cv.pdf']);
        $this->private->putFile('forms/contact/cv.pdf', 'Private CV');
        $result = $this->page(EntryFileHandler::class, 'GET', ['form' => $form, 'entry' => $entry, 'field' => 'cv']);

        static::assertSame(
            ['cv.pdf', 'application/octet-stream', 'Private CV'],
            [$result->download?->filename, $result->download?->mediaType, $this->written($result)],
        );
    }

    #[Test]
    public function keepsAnEntryWhoseFilesCannotBeDeleted(): void
    {
        $refusing = $this->createStub(StorageInterface::class);
        $refusing->method('delete')->willThrowException(new WriteException('The disk is full'));
        $storage = new StorageManager();
        $storage->register('local', $this->public, isPrimary: true);
        $storage->register(UploadStorage::PRIVATE, $refusing);
        $this->setUpHost([StorageManager::class => $storage]);
        $form   = $this->layOutWithUpload()['form'];
        $entry  = $this->addEntry($form, ['cv' => 'forms/contact/cv.pdf']);
        $result = $this->page(EntryDeleteHandler::class, 'POST', ['form' => $form, 'entry' => $entry]);

        static::assertSame(
            [
                [[
                    'level' => 'danger',
                    'text'  => 'Its files could not be deleted, so the entry was kept. The log has the details.',
                ]],
                true,
            ],
            [$result->messages, null !== $this->storedEntry($entry)],
        );
    }

    #[Test]
    public function refusesALinkThatHasExpired(): void
    {
        $form  = $this->layOutWithUpload()['form'];
        $entry = $this->addEntry($form, ['cv' => 'forms/contact/cv.pdf']);
        $this->private->putFile('forms/contact/cv.pdf', 'CV');
        $links = new FileLinks(
            (new SigningKey($this->database))->value(),
            new FrozenClock(new DateTimeImmutable('2026-10-13 08:59')),
        );

        static::assertSame(401, $this->fetch($links->link('', $entry, 'cv'))->getStatusCode());
    }

    #[Test]
    public function refusesALinkThatIsNotSigned(): void
    {
        $form  = $this->layOutWithUpload()['form'];
        $entry = $this->addEntry($form, ['cv' => 'forms/contact/cv.pdf']);
        $this->private->putFile('forms/contact/cv.pdf', 'CV');

        static::assertSame(
            401,
            $this->fetch(
                "/api/extension/formbuilder/file?entry={$entry}&field=cv&expires=1900000000&signature=x",
            )->getStatusCode(),
        );
    }

    #[Test]
    public function saysAFileIsGoneWhenStorageHasItNoLonger(): void
    {
        $form   = $this->layOutWithUpload()['form'];
        $entry  = $this->addEntry($form, ['cv' => 'forms/contact/cv.pdf']);
        $result = $this->page(EntryFileHandler::class, 'GET', ['form' => $form, 'entry' => $entry, 'field' => 'cv']);

        static::assertSame(
            [
                "/admin/extension/formbuilder/entry?form={$form}&entry={$entry}",
                [['level' => 'danger', 'text' => 'That file is no longer in storage.']],
            ],
            [$result->url, $result->messages],
        );
    }

    #[Test]
    public function saysAFileIsGoneWithoutPrivateStorage(): void
    {
        $this->setUpHost();
        $form  = $this->layOutWithUpload()['form'];
        $entry = $this->addEntry($form, ['cv' => 'forms/contact/cv.pdf']);

        static::assertSame(
            [['level' => 'danger', 'text' => 'That file is no longer in storage.']],
            $this->page(EntryFileHandler::class, 'GET', [
                'form'  => $form,
                'entry' => $entry,
                'field' => 'cv',
            ])->messages,
        );
    }

    #[Test]
    public function saysALinkedFileIsGone(): void
    {
        $form    = $this->layOutWithUpload()['form'];
        $entry   = $this->addEntry($form, ['cv' => 'forms/contact/cv.pdf']);
        $links   = FileLinks::of($this->context());
        $gone    = $this->fetch($links->link('', $entry, 'cv'));
        $noEntry = $this->fetch($links->link('', 99, 'cv'));

        static::assertSame(
            [404, 'This file is no longer available.', 404],
            [$gone->getStatusCode(), (string) $gone->getBody(), $noEntry->getStatusCode()],
        );
    }

    #[Test]
    public function sendsAFileToAnyoneWithALinkFromAnEmail(): void
    {
        $mailer = new MemoryMailer();
        $this->setUpUploads([MailerInterface::class => $mailer]);
        $ids = $this->layOutWithUpload();
        $this->repository()->createField($ids['rows'][1], ['type' => 'file', 'name' => 'photo']);
        $this->repository()->createNotification($ids['form'], [
            'name'          => 'Team',
            'to_address'    => 'team@example.com',
            'subject'       => 'New enquiry',
            'body_template' => '<p>CV: {file:cv}. Photo: {file:photo}.</p>',
        ]);
        $entry = $this->addEntry($ids['form'], ['cv' => 'forms/contact/cv.pdf'], 'spam');
        $this->private->putFile('forms/contact/cv.pdf', 'Linked CV');
        (new EntryStatusHandler())->handle(
            (new ServerRequest(
                'POST',
                'https://www.example.com/admin/extension/formbuilder/entry-status',
            ))->withQueryParams(['form' => $ids['form'], 'entry' => $entry, 'status' => 'complete']),
            $this->context(),
        );
        $html = $mailer->sent()[0]->html ?? '';
        preg_match('#https://www\.example\.com(/api/extension/formbuilder/file\?[^"<\s]+)\. Photo: \.#', $html, $link);
        $response = $this->fetch(html_entity_decode($link[1] ?? ''));

        static::assertSame(
            [200, 'attachment; filename="cv.pdf"', 'Linked CV'],
            [
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Disposition'),
                (string) $response->getBody(),
            ],
        );
    }

    #[Test]
    public function sendsAnotherFormsEntryBackToTheList(): void
    {
        $this->layOutWithUpload();
        $other  = $this->addForm('Other', 'other');
        $entry  = $this->addEntry(1, ['cv' => 'forms/contact/cv.pdf']);
        $result = $this->page(EntryFileHandler::class, 'GET', ['form' => $other, 'entry' => $entry, 'field' => 'cv']);

        static::assertSame([ResultKind::Redirect, "/admin/extension/formbuilder/entries?form={$other}"], [
            $result->kind,
            $result->url,
        ]);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpUploads();
    }

    private function fetch(string $path): ResponseInterface
    {
        return $this->host->handle((new Psr17Factory())->createServerRequest('GET', $path));
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
