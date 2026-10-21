<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Fields;

use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Formbuilder\Admin\Fields\GroupInsertHandler;
use Contenir\Extension\Formbuilder\Admin\Fields\RowInsertHandler;
use Contenir\Extension\Formbuilder\Admin\Fields\SectionInsertHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function str_contains;

/**
 * Adding an empty section, group or row in place: drawn alone for the
 * editor's script, or back to the fields screen for a page.
 */
#[Group('integration')]
#[Group('handler')]
final class InsertHandlersTest extends TestCase
{
    use FieldsEditorTrait;

    /**
     * @return array<string, array{class-string, string, string}>
     */
    public static function insertProvider(): array
    {
        return [
            'a section' => [SectionInsertHandler::class, '', 'forms/fields/section'],
            'a group'   => [GroupInsertHandler::class, 'section', 'forms/fields/group'],
            'a row'     => [RowInsertHandler::class, 'group', 'forms/fields/row'],
        ];
    }

    /**
     * @return array<string, array{class-string, string, string}>
     */
    public static function messageProvider(): array
    {
        return [
            'a section' => [SectionInsertHandler::class, '', 'Section inserted.'],
            'a group'   => [GroupInsertHandler::class, 'section', 'Group inserted.'],
            'a row'     => [RowInsertHandler::class, 'group', 'Row inserted.'],
        ];
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function partProvider(): array
    {
        return [
            'a group, in another form\'s section' => [GroupInsertHandler::class, 'section'],
            'a row, in another form\'s group'     => [RowInsertHandler::class, 'group'],
        ];
    }

    /**
     * @param class-string $handler
     */
    #[Test]
    #[DataProvider('insertProvider')]
    public function addsAtTheEndAndDrawsItAloneForTheScript(
        string $handler,
        string $inside,
        string $template,
    ): void {
        $ids    = $this->layOut();
        $before = $this->parts($ids, $inside);
        $result = $this->answer($handler, 'POST', $this->query($ids, $inside, $before));

        static::assertSame(
            [ResultKind::Fragment, $template, $before + 1, true, []],
            [
                $result->kind,
                $result->fragment?->name,
                $this->parts($ids, $inside),
                str_contains($this->drawn($result), 'data-formbuilder'),
                $result->messages,
            ],
        );
    }

    /**
     * @param class-string $handler
     */
    #[Test]
    #[DataProvider('insertProvider')]
    public function addsNothingAskedWithoutPosting(string $handler, string $inside): void
    {
        $ids    = $this->layOut();
        $before = $this->parts($ids, $inside);
        $result = $this->answer($handler, 'GET', $this->query($ids, $inside, 0));

        static::assertSame([ResultKind::Redirect, $before], [$result->kind, $this->parts($ids, $inside)]);
    }

    /**
     * @param class-string $handler
     */
    #[Test]
    #[DataProvider('partProvider')]
    public function addsNothingToAnotherFormsPart(string $handler, string $inside): void
    {
        $ids    = $this->layOut();
        $other  = $this->layOut('Newsletter', 'newsletter');
        $before = [$this->parts($ids, $inside), $this->parts($other, $inside)];
        $result = $this->answer($handler, 'POST', [
            'form'     => $ids['form'],
            $inside    => $other[$inside],
            'position' => 0,
        ]);

        static::assertSame(
            [404, ['ok' => false, 'error' => 'not_found'], $before],
            [$result->status, $result->json, [$this->parts($ids, $inside), $this->parts($other, $inside)]],
        );
    }

    /**
     * @param class-string $handler
     */
    #[Test]
    #[DataProvider('messageProvider')]
    public function goesBackToTheFieldsScreenForAPage(string $handler, string $inside, string $message): void
    {
        $ids    = $this->layOut();
        $result = $this->page($handler, 'POST', $this->query($ids, $inside, 0));

        static::assertSame(
            [
                ResultKind::Redirect,
                "/admin/extension/formbuilder/fields?form={$ids['form']}",
                [['level' => 'success', 'text' => $message]],
            ],
            [$result->kind, $result->url, $result->messages],
        );
    }

    #[Test]
    public function insertsAtThePositionItsAddressNames(): void
    {
        $ids = $this->layOut();
        $this->answer(
            RowInsertHandler::class,
            'POST',
            ['form' => $ids['form'], 'group' => $ids['group'], 'position' => 0],
            ['position' => 5],
        );

        $rows = $this->form($ids['form'])?->sections[0]->groups[0]->rows ?? [];

        static::assertSame([0, 2, 1], [
            count($rows[0]->fields ?? [1]),
            count($rows[1]->fields ?? []),
            count($rows[2]->fields ?? []),
        ]);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * How many parts the insert adds to: the form's sections, a section's
     * groups or a group's rows.
     *
     * @param array{form: int, section: int, group: int, rows: list<int>, fields: list<int>} $ids
     */
    private function parts(array $ids, string $inside): int
    {
        $form = $this->form($ids['form']) ?? throw new LogicException('The form is gone');

        return match ($inside) {
            'section' => count($form->sections[0]->groups),
            'group'   => count($form->sections[0]->groups[0]->rows),
            default   => count($form->sections),
        };
    }

    /**
     * @param array{form: int, section: int, group: int, rows: list<int>, fields: list<int>} $ids
     *
     * @return array<string, int>
     */
    private function query(array $ids, string $inside, int $position): array
    {
        $query = ['form' => $ids['form'], 'position' => $position];

        return '' === $inside ? $query : $query + [$inside => $ids[$inside]];
    }
}
