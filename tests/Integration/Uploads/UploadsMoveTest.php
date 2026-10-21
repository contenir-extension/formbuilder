<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Uploads;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\UploadsMoveHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\UploadsTrait;
use Contenir\FormBuilder\Repository\PhpDbEntryRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

/**
 * Move uploads to private storage, on the Forms home: every form's uploads
 * leave public storage, an entry follows a file private storage renamed,
 * and the Forms home says how it went (FRM-2).
 */
#[Group('integration')]
#[Group('handler')]
final class UploadsMoveTest extends TestCase
{
    use UploadsTrait;

    /**
     * @return array<string, array{list<string>, int}>
     */
    public static function grantProvider(): array
    {
        return [
            'a role viewing'  => [['forms:view'], 403],
            'a role updating' => [['forms:update'], 302],
        ];
    }

    /**
     * @return array<string, array{int, int, string, string}>
     */
    public static function reportProvider(): array
    {
        return [
            'one moved'         => [1, 0, 'success', 'Moved 1 upload to private storage.'],
            'two moved'         => [2, 0, 'success', 'Moved 2 uploads to private storage.'],
            'none left'         => [0, 0, 'success', 'No uploads were left in public storage.'],
            'one missing'       => [1, 1, 'warning', 'Moved 1 upload to private storage. 1 upload could not be found.'],
            'none, two missing' => [
                0,
                2,
                'warning',
                'No uploads were left in public storage. 2 uploads could not be found.',
            ],
        ];
    }

    #[Test]
    public function movesAnEntrysOtherFilesWhenOneIsMissing(): void
    {
        $ids = $this->layOutWithUpload();
        $this->repository()->createField($ids['rows'][1], ['type' => 'file', 'name' => 'photo']);
        $this->addEntry($ids['form'], ['cv' => 'forms/contact/gone.txt', 'photo' => 'forms/contact/photo.txt']);
        $this->public->putFile('forms/contact/photo.txt', 'A photo, as text');
        $this->move();

        static::assertTrue($this->private->exists('forms/contact/photo.txt'));
    }

    #[Test]
    public function movesEntriesBeyondTheFirstBatch(): void
    {
        $form = $this->layOutWithUpload()['form'];
        for ($entry = 1; $entry <= 201; ++$entry) {
            $this->addEntry($form, ['cv' => "forms/contact/{$entry}.txt"]);
            $this->public->putFile("forms/contact/{$entry}.txt", "Notes {$entry}");
        }

        static::assertSame(
            [[['level' => 'success', 'text' => 'Moved 201 uploads to private storage.']], true],
            [$this->move()->messages, $this->private->exists('forms/contact/1.txt')],
        );
    }

    #[Test]
    public function movesEveryFormsUploadsAndFollowsARenamedFile(): void
    {
        $form  = $this->layOutWithUpload()['form'];
        $notes = $this->addEntry($form, ['cv' => 'forms/contact/notes.pdf']);
        $this->addEntry($form, ['cv' => 'forms/contact/gone.pdf']);
        $this->public->putFile('forms/contact/notes.pdf', 'Plain notes, not a PDF');
        $result = $this->move();

        static::assertSame(
            [
                [['level' => 'warning', 'text' => 'Moved 1 upload to private storage. 1 upload could not be found.']],
                ['forms/contact/notes.txt'],
                false,
            ],
            [
                $result->messages,
                array_column((new PhpDbEntryRepository($this->database))->fetchValues($notes), 'value_text'),
                $this->public->exists('forms/contact/notes.pdf'),
            ],
        );
    }

    #[Test]
    public function movesNothingWhenAskedByGet(): void
    {
        $form = $this->layOutWithUpload()['form'];
        $this->addEntry($form, ['cv' => 'forms/contact/notes.txt']);
        $this->public->putFile('forms/contact/notes.txt', 'Notes');
        $result = (new UploadsMoveHandler())->handle(
            new ServerRequest('GET', '/admin/extension/formbuilder/uploads-move'),
            $this->context(),
        );

        static::assertSame(
            ['/admin/extension/formbuilder/forms', [], true],
            [$result->url, $result->messages, $this->public->exists('forms/contact/notes.txt')],
        );
    }

    #[Test]
    #[DataProvider('grantProvider')]
    public function needsUpdate(array $grants, int $status): void
    {
        $response = $this->host->handle(
            (new Psr17Factory())->createServerRequest('POST', '/admin/extension/formbuilder/uploads-move'),
            $grants,
        );

        static::assertSame($status, $response->getStatusCode());
    }

    #[Test]
    #[DataProvider('reportProvider')]
    public function saysHowManyItMovedAndHowManyItCouldNotFind(
        int $moved,
        int $missing,
        string $level,
        string $text,
    ): void {
        $form = $this->layOutWithUpload()['form'];
        for ($file = 1; $file <= $moved; ++$file) {
            $this->addEntry($form, ['cv' => "forms/contact/{$file}.txt"]);
            $this->public->putFile("forms/contact/{$file}.txt", 'Notes');
        }
        for ($file = 1; $file <= $missing; ++$file) {
            $this->addEntry($form, ['cv' => "forms/contact/missing-{$file}.txt"]);
        }
        $this->addEntry($form, ['cv' => 'forms/contact/kept.txt']);
        $this->private->putFile('forms/contact/kept.txt', 'Notes');

        static::assertSame([['level' => $level, 'text' => $text]], $this->move()->messages);
    }

    #[Test]
    public function saysWhenTheSiteHasNoPrivateStorage(): void
    {
        $this->setUpHost();

        static::assertSame(
            [['level' => 'danger', 'text' => 'This site has no private storage, so uploads can\'t be moved.']],
            $this->move()->messages,
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpUploads();
    }

    private function move(): HandlerResult
    {
        return (new UploadsMoveHandler())->handle(
            new ServerRequest('POST', '/admin/extension/formbuilder/uploads-move'),
            $this->context(),
        );
    }
}
