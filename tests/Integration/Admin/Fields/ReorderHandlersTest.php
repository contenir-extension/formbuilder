<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Fields;

use Contenir\Extension\Formbuilder\Admin\Fields\FieldReorderHandler;
use Contenir\Extension\Formbuilder\Admin\Fields\RowReorderHandler;
use Contenir\Extension\Formbuilder\Tests\TestAsset\FormShape;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

/**
 * Saving the order the editor's script posts after a drag: a group's rows,
 * or fields within and across rows. Another form's rows and fields are
 * left out, and a request that isn't one is refused.
 */
#[Group('integration')]
#[Group('handler')]
final class ReorderHandlersTest extends TestCase
{
    use FieldsEditorTrait;

    /**
     * @return array<string, array{class-string, string, array<string, mixed>}>
     */
    public static function refusedProvider(): array
    {
        return [
            'rows, asked without posting'    => [RowReorderHandler::class, 'GET', []],
            'rows, of another form\'s group' => [RowReorderHandler::class, 'POST', ['group' => 'other']],
            'fields, asked without posting'  => [FieldReorderHandler::class, 'GET', []],
            'fields, posted not as JSON'     => [FieldReorderHandler::class, 'POST', ['rows' => '{not json']],
            'fields, posted as a number'     => [FieldReorderHandler::class, 'POST', ['rows' => '3']],
        ];
    }

    #[Test]
    public function leavesOutAnotherFormsRowsAndFields(): void
    {
        $ids   = $this->layOut();
        $other = $this->layOut('Newsletter', 'newsletter');
        $this->answer(
            FieldReorderHandler::class,
            'POST',
            ['form' => $ids['form']],
            ['rows' => (string) json_encode([
                $other['rows'][1] => [$other['fields'][2], $ids['fields'][2]],
                $ids['rows'][0]   => [$ids['fields'][1], $other['fields'][0], 'x', $ids['fields'][0]],
            ])],
        );

        static::assertSame(
            [
                ['email', 'name',  'message'],
                ['name',  'email', 'message'],
            ],
            [FormShape::names($this->form($ids['form'])), FormShape::names($this->form($other['form']))],
        );
    }

    #[Test]
    public function movesAFieldIntoAnotherRow(): void
    {
        $ids = $this->layOut();
        [$name, $email, $message] = $ids['fields'];
        $result = $this->answer(
            FieldReorderHandler::class,
            'POST',
            ['form' => $ids['form']],
            ['rows' => (string) json_encode([
                $ids['rows'][0] => [$email],
                $ids['rows'][1] => [$name, $message],
            ])],
        );

        static::assertSame(
            [['ok' => true], ['email', 'name', 'message']],
            [$result->json, FormShape::names($this->form($ids['form']))],
        );
    }

    #[Test]
    public function putsAGroupsRowsInTheOrderPosted(): void
    {
        $ids    = $this->layOut();
        $other  = $this->layOut('Newsletter', 'newsletter');
        $result = $this->answer(
            RowReorderHandler::class,
            'POST',
            ['form' => $ids['form'], 'group' => $ids['group']],
            [
                'rows' => (string) json_encode([$ids['rows'][1], $other['rows'][0], $ids['rows'][0]]),
            ],
        );

        static::assertSame(
            [['ok' => true], ['message', 'name', 'email'], ['name', 'email', 'message']],
            [$result->json, FormShape::names($this->form($ids['form'])), FormShape::names($this->form($other['form']))],
        );
    }

    /**
     * @param class-string         $handler
     * @param array<string, mixed> $body
     */
    #[Test]
    #[DataProvider('refusedProvider')]
    public function refusesARequestThatIsNotAReorder(string $handler, string $method, array $body): void
    {
        $ids    = $this->layOut();
        $other  = $this->layOut('Newsletter', 'newsletter');
        $group  = 'other' === ($body['group'] ?? null) ? $other['group'] : $ids['group'];
        $result = $this->answer(
            $handler,
            $method,
            ['form' => $ids['form'], 'group' => $group],
            $body
                + [
                    'rows' => (string) json_encode([$ids['rows'][1], $ids['rows'][0]]),
                ],
        );

        static::assertSame(
            [400, ['ok' => false, 'error' => 'bad_request'], ['name', 'email', 'message']],
            [$result->status, $result->json, FormShape::names($this->form($ids['form']))],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }
}
