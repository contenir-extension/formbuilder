<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Fields;

use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Api\Handler\Tab;
use Contenir\Extension\Formbuilder\Admin\Fields\FieldsHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function str_contains;
use function substr_count;

/**
 * A form's Fields tab: its layout drawn for the editor's scripts, guarded
 * as the built-in module's fields screen was.
 */
#[Group('integration')]
#[Group('handler')]
final class FieldsHandlerTest extends TestCase
{
    use FieldsEditorTrait;

    /**
     * @return array<string, array{list<string>, int}>
     */
    public static function grantProvider(): array
    {
        return [
            'a role updating forms' => [['forms:update'], 200],
            'a role only viewing'   => [['forms:view'], 403],
            'a role adding forms'   => [['forms:add'], 403],
            'a role with no forms'  => [[], 403],
        ];
    }

    #[Test]
    public function drawsEverySectionGroupRowAndFieldForTheScripts(): void
    {
        $ids  = $this->layOut();
        $html = $this->drawn($this->page(FieldsHandler::class, 'GET', ['form' => $ids['form']]));

        static::assertSame(
            [1, 1, 2, 3, true, true, true, true],
            [
                substr_count($html, needle: '<section class="card builder__section"'),
                substr_count($html, needle: 'data-formbuilder-rows '),
                substr_count($html, needle: 'data-formbuilder-row '),
                substr_count($html, needle: 'data-formbuilder-field '),
                str_contains(
                    $html,
                    "data-row-reorder-url=\"/admin/extension/formbuilder/row-reorder?form={$ids['form']}&amp;group={$ids['group']}\"",
                ),
                str_contains(
                    $html,
                    '<div data-formbuilder-token hidden><input type="hidden" name="token" value="in-memory"></div>',
                ),
                str_contains(
                    $html,
                    'href="/admin/extension/formbuilder/field-add?form=__formId__&amp;row=__rowId__&amp;type=email"',
                ),
                str_contains(
                    $html,
                    'data-formbuilder-delete data-formbuilder-target="__formTarget__" href="__deleteUrl__"',
                ),
            ],
        );
    }

    /**
     * @param list<string> $grants
     */
    #[Test]
    #[DataProvider('grantProvider')]
    public function needsUpdateInTheFormsArea(array $grants, int $status): void
    {
        $ids      = $this->layOut();
        $response = $this->host->handle(
            (new Psr17Factory())->createServerRequest('GET', "/admin/extension/formbuilder/fields?form={$ids['form']}"),
            $grants,
        );

        static::assertSame($status, $response->getStatusCode());
    }

    #[Test]
    public function sendsAFormThatIsGoneBackToTheFormsHome(): void
    {
        $result = $this->page(FieldsHandler::class, 'GET', ['form' => 99]);

        static::assertSame(
            ['/admin/extension/formbuilder/forms', 'That form no longer exists.'],
            [$result->url, $result->messages[0]['text'] ?? null],
        );
    }

    #[Test]
    public function showsTheFormsTitleWithItsTabsFieldsCurrent(): void
    {
        $ids    = $this->layOut();
        $result = $this->page(FieldsHandler::class, 'GET', ['form' => $ids['form']]);
        $tabs   = $result->template->tabs ?? [];

        static::assertSame(
            [ResultKind::Template, 'forms/fields', 'Contact', ['fields'], 6],
            [
                $result->kind,
                $result->template?->name,
                $result->template?->title,
                array_values(array_map(
                    static fn(Tab $tab): string => $tab->handler,
                    array_filter($tabs, static fn(Tab $tab): bool => $tab->current),
                )),
                count($tabs),
            ],
        );
    }

    #[Test]
    public function tellsAScriptAFormThatIsGoneWasNotFound(): void
    {
        $result = $this->answer(FieldsHandler::class, 'GET', ['form' => 99]);

        static::assertSame([404, ['ok' => false, 'error' => 'not_found']], [$result->status, $result->json]);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }
}
