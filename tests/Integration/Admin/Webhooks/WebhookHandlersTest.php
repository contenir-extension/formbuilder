<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Webhooks;

use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Formbuilder\Admin\Webhooks\WebhookAddHandler;
use Contenir\Extension\Formbuilder\Admin\Webhooks\WebhookDeleteHandler;
use Contenir\Extension\Formbuilder\Admin\Webhooks\WebhookHandler;
use Contenir\Extension\Formbuilder\Admin\Webhooks\WebhooksHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use Contenir\FormBuilder\Definition\WebhookDefinition;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;
use function count;
use function str_contains;

/**
 * A form's Integrations tab and the screens about each webhook, as the
 * built-in module had them.
 */
#[Group('integration')]
#[Group('handler')]
final class WebhookHandlersTest extends TestCase
{
    use FieldsEditorTrait;

    private string $signingKey = 'k3y-for-tests';

    /**
     * @return array<string, array{string, string, list<string>, int}>
     */
    public static function grantProvider(): array
    {
        return [
            'the list, to a role viewing'  => ['GET', 'webhooks', ['forms:view'], 200],
            'adding, by a role adding'     => ['GET', 'webhook-add', ['forms:add'], 200],
            'adding, by a role updating'   => ['GET', 'webhook-add', ['forms:update'], 403],
            'editing, by a role viewing'   => ['GET', 'webhook', ['forms:view'], 403],
            'deleting, by a role updating' => ['POST', 'webhook-delete', ['forms:update'], 403],
            'deleting, by a role deleting' => ['POST', 'webhook-delete', ['forms:delete'], 302],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function problemProvider(): array
    {
        return [
            'no URL'            => ['', 'A target URL is required.'],
            'not a web address' => ['ftp://files.example.com', 'URL must start with http:// or https://.'],
        ];
    }

    #[Test]
    public function addsAWebhookAndGoesBackToTheList(): void
    {
        $ids    = $this->layOut();
        $result = $this->page(
            WebhookAddHandler::class,
            'POST',
            ['form' => $ids['form']],
            [
                'name'    => '',
                'url'     => 'https://hooks.example.com/a',
                'method'  => 'put',
                'secret'  => " {$this->signingKey} ",
                'headers' => "X-One: 1\n\nnot a header\n: no name\nX-Two:  two words ",
                'enabled' => '1',
            ],
        );

        static::assertSame(
            [
                ResultKind::Redirect,
                "/admin/extension/formbuilder/webhooks?form={$ids['form']}",
                [['level' => 'success', 'text' => 'Webhook added.']],
                [[
                    'Webhook',
                    'https://hooks.example.com/a',
                    'PUT',
                    $this->signingKey,
                    ['X-One' => '1', 'X-Two' => 'two words'],
                    true,
                ]],
            ],
            [$result->kind, $result->url, $result->messages, $this->webhooks($ids['form'])],
        );
    }

    #[Test]
    public function deletesOnlyThisFormsWebhookWhenPosted(): void
    {
        $ids   = $this->layOut();
        $other = $this->layOut('Newsletter', 'newsletter');
        $mine  = $this->repository()->createWebhook($ids['form'], [
            'name' => 'Mine',
            'url'  => 'https://a.example.com',
        ]);
        $theirs = $this->repository()->createWebhook($other['form'], [
            'name' => 'Theirs',
            'url'  => 'https://b.example.com',
        ]);
        $this->page(WebhookDeleteHandler::class, 'GET', ['form' => $ids['form'], 'webhook' => $mine]);
        $this->page(WebhookDeleteHandler::class, 'POST', ['form' => $ids['form'], 'webhook' => $theirs]);
        $result = $this->page(WebhookDeleteHandler::class, 'POST', ['form' => $ids['form'], 'webhook' => $mine]);

        static::assertSame(
            [[['level' => 'success', 'text' => 'Webhook deleted.']], [], 1],
            [$result->messages, $this->webhooks($ids['form']), count($this->webhooks($other['form']))],
        );
    }

    #[Test]
    public function deletesTheWebhookItsAddressNames(): void
    {
        $ids   = $this->layOut();
        $first = $this->repository()->createWebhook($ids['form'], [
            'name' => 'First',
            'url'  => 'https://a.example.com',
        ]);
        $second = $this->repository()->createWebhook($ids['form'], [
            'name' => 'Second',
            'url'  => 'https://b.example.com',
        ]);
        $result = $this->page(
            WebhookDeleteHandler::class,
            'POST',
            ['form' => $ids['form'], 'webhook' => $second],
            ['webhook' => $first],
        );

        static::assertSame(
            ["/admin/extension/formbuilder/webhooks?form={$ids['form']}", ['First']],
            [$result->url, array_map(static fn(array $webhook): mixed => $webhook[0], $this->webhooks($ids['form']))],
        );
    }

    #[Test]
    public function drawsAnEmptyListWithAddWebhook(): void
    {
        $ids  = $this->layOut();
        $html = $this->drawn($this->page(WebhooksHandler::class, 'GET', ['form' => $ids['form']]));

        static::assertSame(
            [true, true],
            [str_contains($html, 'No webhooks'), str_contains($html, "webhook-add?form={$ids['form']}")],
        );
    }

    #[Test]
    public function drawsEachWebhookWithItsDeleteCarryingTheToken(): void
    {
        $ids = $this->layOut();
        $this->repository()->createWebhook($ids['form'], [
            'name'   => 'Zapier',
            'url'    => 'https://hooks.example.com/z',
            'secret' => $this->signingKey,
        ]);
        $html = $this->drawn($this->page(WebhooksHandler::class, 'GET', ['form' => $ids['form']]));

        static::assertSame(
            [true, true, true, false],
            [
                str_contains($html, '<code class="text--xs webhook__url">https://hooks.example.com/z</code>'),
                str_contains($html, 'data-confirm="Delete the Zapier webhook?">'),
                str_contains($html, '<input type="hidden" name="token" value="in-memory">'),
                str_contains($html, 'No webhooks'),
            ],
        );
    }

    #[Test]
    public function editsAWebhookAndShowsItsSettings(): void
    {
        $ids = $this->layOut();
        $this->repository()->createWebhook($ids['form'], ['name' => 'Earlier', 'url' => 'https://e.example.com']);
        $webhook = $this->repository()->createWebhook($ids['form'], [
            'name'    => 'Zapier',
            'url'     => 'https://hooks.example.com/z',
            'headers' => ['X-One' => '1', 'X-Two' => '2'],
            'enabled' => false,
        ]);
        $shown  = $this->page(WebhookHandler::class, 'GET', ['form' => $ids['form'], 'webhook' => $webhook]);
        $result = $this->page(
            WebhookHandler::class,
            'POST',
            ['form' => $ids['form'], 'webhook' => $webhook],
            [
                'name' => 'Make',
                'url'  => 'http://hooks.example.com/m',
            ],
        );

        static::assertSame(
            [
                ['Zapier', "X-One: 1\nX-Two: 2", false, 'webhooks'],
                [['level' => 'success', 'text' => 'Webhook saved.']],
                [
                    ['Earlier', 'https://e.example.com', 'POST', null, [], true],
                    ['Make', 'http://hooks.example.com/m', 'POST', null, [], false],
                ],
            ],
            [
                [
                    $shown->screen?->title,
                    $shown->screen?->form?->field('headers')?->default,
                    $shown->screen?->form?->field('enabled')?->default,
                    array_map(
                        static fn($tab) => $tab->handler,
                        array_filter($shown->screen->tabs ?? [], static fn($tab) => $tab->current),
                    )[4] ?? null,
                ],
                $result->messages,
                $this->webhooks($ids['form']),
            ],
        );
    }

    /**
     * @param list<string> $grants
     */
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
    #[DataProvider('problemProvider')]
    public function saysWhatStopsAWebhookSaving(string $url, string $problem): void
    {
        $ids     = $this->layOut();
        $webhook = $this->repository()->createWebhook($ids['form'], [
            'name' => 'Zapier',
            'url'  => 'https://a.example.com',
        ]);
        $added = $this->page(
            WebhookAddHandler::class,
            'POST',
            ['form' => $ids['form']],
            ['url' => $url, 'headers' => 'X: 1'],
        );
        $edited = $this->page(
            WebhookHandler::class,
            'POST',
            ['form' => $ids['form'], 'webhook' => $webhook],
            ['url' => $url],
        );

        static::assertSame(
            [
                ResultKind::Screen,
                [['level' => 'danger', 'text' => $problem]],
                'X: 1',
                [['level' => 'danger', 'text' => $problem]],
                1,
            ],
            [
                $added->kind,
                $added->messages,
                $added->screen?->form?->field('headers')?->default,
                $edited->messages,
                count($this->webhooks($ids['form'])),
            ],
        );
    }

    #[Test]
    public function sendsAnotherFormsWebhookBackToTheList(): void
    {
        $ids     = $this->layOut();
        $other   = $this->layOut('Newsletter', 'newsletter');
        $webhook = $this->repository()->createWebhook($other['form'], [
            'name' => 'Theirs',
            'url'  => 'https://b.example.com',
        ]);
        $result = $this->page(
            WebhookHandler::class,
            'POST',
            ['form' => $ids['form'], 'webhook' => $webhook],
            ['url' => 'https://x.example.com'],
        );

        static::assertSame(
            [
                ResultKind::Redirect,
                "/admin/extension/formbuilder/webhooks?form={$ids['form']}",
                [],
                'https://b.example.com',
            ],
            [$result->kind, $result->url, $result->messages, $this->webhooks($other['form'])[0][1] ?? null],
        );
    }

    #[Test]
    public function showsANewWebhooksSettingsWithNothingToFix(): void
    {
        $ids    = $this->layOut();
        $result = $this->page(WebhookAddHandler::class, 'GET', ['form' => $ids['form']]);

        static::assertSame(['New webhook', []], [$result->screen?->title, $result->messages]);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * The form's webhooks as stored: name, URL, method, secret, headers and
     * whether each sends.
     *
     * @return list<list<mixed>>
     */
    private function webhooks(int $form): array
    {
        return array_map(
            static fn(WebhookDefinition $webhook): array => [
                $webhook->name,
                $webhook->url,
                $webhook->method,
                $webhook->secret,
                $webhook->headers,
                $webhook->enabled,
            ],
            $this->form($form)->webhooks ?? [],
        );
    }
}
