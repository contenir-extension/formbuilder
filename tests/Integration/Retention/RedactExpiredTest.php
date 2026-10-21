<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Retention;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\EntriesRedactHandler;
use Contenir\Extension\Formbuilder\Retention\RedactExpiredTask;
use Contenir\Extension\Formbuilder\Tests\Trait\UploadsTrait;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

/**
 * Entries kept past their form's retention period are redacted, from the
 * Forms home's Redact expired entries or every night (FRM-3).
 */
#[Group('integration')]
#[Group('handler')]
final class RedactExpiredTest extends TestCase
{
    use UploadsTrait;

    /**
     * @return array<string, array{list<string>, int}>
     */
    public static function grantProvider(): array
    {
        return [
            'a role updating' => [['forms:update'], 403],
            'a role deleting' => [['forms:delete'], 302],
        ];
    }

    #[Test]
    public function asksFirstByGetSayingHowManyEntriesItWouldTake(): void
    {
        $form  = $this->expiring();
        $entry = $this->addEntry($form, ['name' => 'Ada'], at: '2026-09-01 10:00');
        $this->addEntry($form, ['name' => 'Bea'], at: '2026-10-19 10:00');
        $result = (new EntriesRedactHandler())->handle(
            new ServerRequest('GET', '/admin/extension/formbuilder/entries-redact'),
            $this->context(),
        );

        static::assertSame(
            ['forms/entries-redact', ['expired' => 1], 'Forms', 'complete'],
            [
                $result->template?->name,
                $result->template?->values,
                $result->template?->parent?->title,
                $this->storedEntry($entry)['status'] ?? null,
            ],
        );
    }

    #[Test]
    public function countsNothingWhereNoFormHasARetentionPeriod(): void
    {
        $this->addEntry($this->layOutWithUpload()['form'], ['name' => 'Ada'], at: '2025-01-01 10:00');
        $result = (new EntriesRedactHandler())->handle(
            new ServerRequest('GET', '/admin/extension/formbuilder/entries-redact'),
            $this->context(),
        );

        static::assertSame(['expired' => 0], $result->template?->values);
    }

    #[Test]
    public function keepsAnEntryWhoseFilesHaveNowhereToBeDeletedFrom(): void
    {
        $this->setUpHost();
        $form = $this->expiring();
        $this->addEntry($form, ['cv' => 'forms/contact/cv.pdf'], at: '2026-09-01 10:00');

        static::assertSame(
            [[
                'level' => 'warning',
                'text' => 'No entries are past their form\'s retention period. 1 entry was kept because its files could not be deleted; it will be tried again next time.',
            ]],
            $this->redact()->messages,
        );
    }

    #[Test]
    public function leavesEntriesWithinTheirPeriodAndFormsWithoutOne(): void
    {
        $form    = $this->expiring();
        $recent  = $this->addEntry($form, ['name' => 'Ada'], at: '2026-10-10 10:00');
        $forever = $this->addForm('Forever', 'forever');
        $old     = $this->addEntry($forever, ['name' => 'Grace'], at: '2020-01-01 10:00');

        static::assertSame(
            [
                [['level' => 'success', 'text' => 'No entries are past their form\'s retention period.']],
                'complete',
                'complete',
            ],
            [
                $this->redact()->messages,
                $this->storedEntry($recent)['status'] ?? null,
                $this->storedEntry($old)['status'] ?? null,
            ],
        );
    }

    #[Test]
    #[DataProvider('grantProvider')]
    public function needsDelete(array $grants, int $status): void
    {
        $response = $this->host->handle(
            (new Psr17Factory())->createServerRequest('POST', '/admin/extension/formbuilder/entries-redact'),
            $grants,
        );

        static::assertSame($status, $response->getStatusCode());
    }

    #[Test]
    public function redactsEntriesPastTheirFormsPeriodWithTheirFiles(): void
    {
        $form  = $this->expiring();
        $entry = $this->addEntry($form, ['name' => 'Ada', 'cv' => 'forms/contact/cv.pdf'], at: '2026-09-01 10:00');
        $this->private->putFile('forms/contact/cv.pdf', 'CV');

        static::assertSame(
            [
                [['level' => 'success', 'text' => 'Redacted 1 entry on 1 form.']],
                'redacted',
                false,
            ],
            [
                $this->redact()->messages,
                $this->storedEntry($entry)['status'] ?? null,
                $this->private->exists('forms/contact/cv.pdf'),
            ],
        );
    }

    #[Test]
    public function redactsEveryNightAndLogsWhatItDid(): void
    {
        $this->addEntry($this->expiring(), ['name' => 'Ada'], at: '2026-09-01 10:00');
        (new RedactExpiredTask())->run($this->context());

        static::assertSame(['Redacted 1 entry on 1 form.'], array_column($this->host->logs(), 'message'));
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpUploads();
    }

    /**
     * A form with an upload field "cv" that keeps its entries thirty days.
     */
    private function expiring(): int
    {
        $form = $this->layOutWithUpload()['form'];
        $this->repository()->updateForm($form, ['retention_days' => 30]);

        return $form;
    }

    private function redact(): HandlerResult
    {
        return (new EntriesRedactHandler())->handle(
            new ServerRequest('POST', '/admin/extension/formbuilder/entries-redact'),
            $this->context(),
        );
    }
}
