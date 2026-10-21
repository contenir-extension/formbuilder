<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Uploads;

use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Formbuilder\Tests\Trait\UploadsTrait;
use Contenir\Extension\Formbuilder\Uploads\Moved;
use Contenir\Extension\Formbuilder\Uploads\UploadStorage;
use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\GroupDefinition;
use Contenir\FormBuilder\Definition\RowDefinition;
use Contenir\FormBuilder\Definition\SectionDefinition;
use Contenir\Storage\Adapter\InMemoryStorage;
use Contenir\Storage\Exception\WriteException;
use Contenir\Storage\StorageInterface;
use Contenir\Storage\StorageManager;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function stream_get_contents;

/**
 * Where uploads are kept: read from private storage, then public, deleted
 * from both, and moved from public to private.
 */
#[Group('integration')]
final class UploadStorageTest extends TestCase
{
    use UploadsTrait;

    private MemoryLogger $log;

    private static function form(): FormDefinition
    {
        return new FormDefinition(
            id: 9,
            slug: 'contact',
            title: 'Contact',
            sections: [new SectionDefinition(1, 'main', groups: [new GroupDefinition(2, rows: [
                new RowDefinition(3, fields: [
                    new FieldDefinition(4, 'file', 'cv'),
                    new FieldDefinition(5, 'file', 'photo'),
                ]),
            ])])],
        );
    }

    #[Test]
    public function deletesAnEntrysFilesFromBoth(): void
    {
        $this->private->putFile('forms/contact/cv.pdf', 'private');
        $this->public->putFile('forms/contact/cv.pdf', 'public');
        $this->public->putFile('forms/contact/photo.png', 'public');
        $this->public->putFile('forms/contact/other.png', 'public');

        static::assertSame(
            [true, false, false, false, true],
            [
                $this->storage()->delete(self::form(), 7, [
                    ['field_name' => 'cv', 'value_text' => 'forms/contact/cv.pdf'],
                    ['field_name' => 'photo', 'value_text' => 'forms/contact/photo.png'],
                ]),
                $this->private->exists('forms/contact/cv.pdf'),
                $this->public->exists('forms/contact/cv.pdf'),
                $this->public->exists('forms/contact/photo.png'),
                $this->public->exists('forms/contact/other.png'),
            ],
        );
    }

    #[Test]
    public function failsAndLogsWhenAFileCannotBeDeleted(): void
    {
        $refusing = $this->createStub(StorageInterface::class);
        $refusing->method('delete')->willThrowException(new WriteException('The disk is full'));
        $storage = new UploadStorage($refusing, $this->public, $this->log);

        static::assertSame(
            [
                false,
                ['The file forms/contact/cv.pdf of entry 7 of form "contact" could not be deleted: The disk is full'],
            ],
            [
                $storage->delete(self::form(), 7, [['field_name' => 'cv', 'value_text' => 'forms/contact/cv.pdf']]),
                array_map(static fn(array $entry): string => $entry['message'], $this->log->entries()),
            ],
        );
    }

    #[Test]
    public function failsToMoveAFileWithoutATemporaryCopy(): void
    {
        $this->public->putFile('forms/contact/cv.pdf', 'public');
        $storage = new UploadStorage($this->private, $this->public, $this->log, static fn(): false => false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No temporary file could be made to move forms/contact/cv.pdf');

        $storage->moveToPrivate('forms/contact/cv.pdf');
    }

    #[Test]
    public function findsNothingAtAPathStorageRefuses(): void
    {
        static::assertNull($this->storage()->open("forms/\0/cv.pdf"));
    }

    #[Test]
    public function findsNothingWhenNeitherHasTheFile(): void
    {
        static::assertSame(
            [null, null],
            [$this->storage()->open('forms/contact/cv.pdf'), $this->storage()->moveToPrivate('forms/contact/cv.pdf')],
        );
    }

    #[Test]
    public function isLentOnlyWithAPrivateProfile(): void
    {
        $public = new StorageManager();
        $public->register('local', new InMemoryStorage());
        $found = [];
        foreach ([[], [StorageManager::class => $public]] as $services) {
            $this->setUpHost($services);
            $found[] = UploadStorage::of($this->context());
        }
        $this->setUpUploads();
        $found[] = UploadStorage::of($this->context()) instanceof UploadStorage;

        static::assertSame([null, null, true], $found);
    }

    #[Test]
    public function keepsAFileAlreadyPrivateAndDeletesItsPublicCopy(): void
    {
        $this->private->putFile('forms/contact/cv.pdf', 'private');
        $this->public->putFile('forms/contact/cv.pdf', 'public');
        $this->private->putFile('forms/contact/photo.png', 'private');

        static::assertEquals(
            [
                new Moved('forms/contact/cv.pdf', fromPublic: false),
                new Moved('forms/contact/photo.png', fromPublic: false),
                false,
            ],
            [
                $this->storage()->moveToPrivate('forms/contact/cv.pdf'),
                $this->storage()->moveToPrivate('forms/contact/photo.png'),
                $this->public->exists('forms/contact/cv.pdf'),
            ],
        );
    }

    #[Test]
    public function movesAFileFromPublicToPrivate(): void
    {
        $this->public->putFile('forms/contact/notes.txt', 'Some notes');
        $moved = $this->storage()->moveToPrivate('forms/contact/notes.txt');

        static::assertEquals(
            [new Moved('forms/contact/notes.txt', fromPublic: true), 'Some notes', false],
            [
                $moved,
                stream_get_contents($this->private->read('forms/contact/notes.txt')),
                $this->public->exists('forms/contact/notes.txt'),
            ],
        );
    }

    #[Test]
    public function readsPrivateStorageFirstThenPublic(): void
    {
        $this->private->putFile('forms/contact/cv.pdf', 'private');
        $this->public->putFile('forms/contact/cv.pdf', 'public');
        $this->public->putFile('forms/contact/photo.png', 'public');

        static::assertSame(
            ['private', 'public'],
            [
                stream_get_contents($this->storage()->open('forms/contact/cv.pdf') ?? throw new RuntimeException()),
                stream_get_contents($this->storage()->open('forms/contact/photo.png') ?? throw new RuntimeException()),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->log = new MemoryLogger();
        $this->setUpUploads();
    }

    private function storage(): UploadStorage
    {
        return new UploadStorage($this->private, $this->public, $this->log);
    }
}
