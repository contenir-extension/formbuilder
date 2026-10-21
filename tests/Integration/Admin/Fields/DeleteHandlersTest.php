<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Fields;

use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Formbuilder\Admin\Fields\FieldDeleteHandler;
use Contenir\Extension\Formbuilder\Admin\Fields\GroupDeleteHandler;
use Contenir\Extension\Formbuilder\Admin\Fields\RowDeleteHandler;
use Contenir\Extension\Formbuilder\Admin\Fields\SectionDeleteHandler;
use Contenir\Extension\Formbuilder\Tests\TestAsset\FormShape;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Deleting a section, group, row or field, with everything in it: the
 * editor's script learns it is gone, a page goes back to the fields screen,
 * and another form's parts stay as they are.
 */
#[Group('integration')]
#[Group('handler')]
final class DeleteHandlersTest extends TestCase
{
    use FieldsEditorTrait;

    /**
     * @return array<string, array{class-string, string, list<int>, string}>
     */
    public static function deleteProvider(): array
    {
        return [
            'a section' => [SectionDeleteHandler::class, 'section', [0, 0, 0, 0], 'Section deleted.'],
            'a group'   => [GroupDeleteHandler::class, 'group', [1, 0, 0, 0], 'Group deleted.'],
            'a row'     => [RowDeleteHandler::class, 'row', [1, 1, 1, 1], 'Row deleted.'],
            'a field'   => [FieldDeleteHandler::class, 'field', [1, 1, 2, 1], 'Field deleted.'],
        ];
    }

    /**
     * @param class-string $handler
     * @param list<int>    $left
     */
    #[Test]
    #[DataProvider('deleteProvider')]
    public function deletesItAndTellsTheScript(string $handler, string $part, array $left): void
    {
        $ids    = $this->layOut();
        $id     = $this->id($ids, $part);
        $result = $this->answer($handler, 'POST', ['form' => $ids['form'], $part => $id]);

        static::assertSame(
            [['ok' => true, "{$part}_id" => $id], $left, []],
            [$result->json, FormShape::counts($this->form($ids['form'])), $result->messages],
        );
    }

    /**
     * @param class-string $handler
     */
    #[Test]
    #[DataProvider('deleteProvider')]
    public function deletesNothingAskedWithoutPosting(string $handler, string $part): void
    {
        $ids    = $this->layOut();
        $result = $this->page(
            $handler,
            'GET',
            ['form' => $ids['form'], $part => $this->id($ids, $part)],
        );

        static::assertSame(
            [ResultKind::Redirect, [], [1, 1, 2, 2]],
            [$result->kind, $result->messages, FormShape::counts($this->form($ids['form']))],
        );
    }

    /**
     * @param class-string $handler
     * @param list<int>    $left
     */
    #[Test]
    #[DataProvider('deleteProvider')]
    public function goesBackToTheFieldsScreenForAPage(string $handler, string $part, array $left, string $message): void
    {
        $ids    = $this->layOut();
        $result = $this->page(
            $handler,
            'POST',
            ['form' => $ids['form'], $part => $this->id($ids, $part)],
        );

        static::assertSame(
            [ResultKind::Redirect, [['level' => 'success', 'text' => $message]], $left],
            [$result->kind, $result->messages, FormShape::counts($this->form($ids['form']))],
        );
    }

    /**
     * @param class-string $handler
     */
    #[Test]
    #[DataProvider('deleteProvider')]
    public function leavesAnotherFormsPartAsItIs(string $handler, string $part): void
    {
        $ids    = $this->layOut();
        $other  = $this->layOut('Newsletter', 'newsletter');
        $result = $this->answer($handler, 'POST', ['form' => $ids['form'], $part => $this->id($other, $part)]);

        static::assertSame(
            [404, [1, 1, 2, 2]],
            [$result->status, FormShape::counts($this->form($other['form']))],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * The part's id: the section, the group, the first row, or the first row's
     * first field.
     *
     * @param array{form: int, section: int, group: int, rows: list<int>, fields: list<int>} $ids
     */
    private function id(array $ids, string $part): int
    {
        return match ($part) {
            'section' => $ids['section'],
            'group'   => $ids['group'],
            'row'     => $ids['rows'][0],
            default   => $ids['fields'][0],
        };
    }
}
