<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin;

use Contenir\Extension\Api\Handler\Tab;
use Contenir\Extension\Formbuilder\Admin\FormId;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_unique;
use function array_values;

use const SORT_REGULAR;

#[Group('unit')]
final class FormTabsTest extends TestCase
{
    /**
     * @return array<string, array{array<string, string>, array<string, string>, int}>
     */
    public static function formIdProvider(): array
    {
        return [
            'in the address'          => [['form' => '3'], [], 3],
            'posted'                  => [[], ['form' => '4'], 4],
            'the address first'       => [['form' => '3'], ['form' => '4'], 3],
            'not a number'            => [['form' => 'x'], [], 0],
            'not a number, then post' => [['form' => 'x'], ['form' => '4'], 4],
            'none'                    => [[], [], 0],
        ];
    }

    #[Test]
    public function findsNoFormInARequestWithNoBody(): void
    {
        static::assertSame(0, FormId::of(new ServerRequest('GET', '/admin/extension/formbuilder/form')));
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $posted
     */
    #[Test]
    #[DataProvider('formIdProvider')]
    public function findsTheFormARequestIsAbout(array $query, array $posted, int $id): void
    {
        $request = (new ServerRequest('POST', '/admin/extension/formbuilder/form'))->withQueryParams($query)
            ->withParsedBody($posted);

        static::assertSame($id, FormId::of($request));
    }

    #[Test]
    public function givesAFormsSixScreensInTheBuiltInModulesOrder(): void
    {
        static::assertSame(
            [
                ['Entries',       'entries',       'table',       false],
                ['Fields',        'fields',        'layout-list', false],
                ['Preview',       'preview',       'eye',         false],
                ['Notifications', 'notifications', 'mail',        false],
                ['Integrations',  'webhooks',      'send',        false],
                ['Settings',      'form',          'settings',    true],
            ],
            array_map(
                static fn(Tab $tab): array => [$tab->label, $tab->handler, $tab->icon, $tab->current],
                FormTabs::of(3, FormTabs::SETTINGS),
            ),
        );
    }

    #[Test]
    public function leadsEachTabToTheSameForm(): void
    {
        static::assertSame(
            [['form' => 7]],
            array_values(array_unique(
                array_map(static fn(Tab $tab): array => $tab->query, FormTabs::of(7, FormTabs::FIELDS)),
                SORT_REGULAR,
            )),
        );
    }

    #[Test]
    public function mountsTheAddOnsHandlersWhereTheApiDoes(): void
    {
        static::assertSame(
            ['/admin/extension/formbuilder/fields', '/admin/extension/formbuilder/fields?form=3&step=2'],
            [Urls::handler('fields'), Urls::handler('fields', ['form' => 3, 'step' => 2])],
        );
    }

    #[Test]
    public function sendsAMissingFormBackToTheFormsHome(): void
    {
        $result = FormId::missing();

        static::assertSame(
            ['/admin/extension/formbuilder/forms', [['level' => 'warning', 'text' => 'That form no longer exists.']]],
            [$result->url, $result->messages],
        );
    }
}
