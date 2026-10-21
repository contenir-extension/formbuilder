<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Webhooks;

use Contenir\Extension\Formbuilder\Admin\Webhooks\WebhookInput;
use Contenir\FormBuilder\Definition\WebhookDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class WebhookInputTest extends TestCase
{
    private string $key = 'k3y-for-tests';

    /**
     * @return array<string, array{string, string}>
     */
    public static function methodProvider(): array
    {
        return [
            'PUT, in any case' => ['put', 'PUT'],
            'POST'             => ['POST', 'POST'],
            'anything else'    => ['DELETE', 'POST'],
            'nothing'          => ['', 'POST'],
        ];
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function urlProvider(): array
    {
        return [
            'https'                => ['https://a.example.com', []],
            'http, in capitals'    => ['HTTP://A.EXAMPLE.COM', []],
            'a web address inside' => ['see https://a.example.com', ['URL must start with http:// or https://.']],
            'nothing'              => ['', ['A target URL is required.']],
        ];
    }

    /**
     * @param list<string> $problems
     */
    #[Test]
    #[DataProvider('urlProvider')]
    public function needsAWebAddress(string $url, array $problems): void
    {
        static::assertSame($problems, WebhookInput::fromBody(['url' => $url])->problems());
    }

    #[Test]
    public function readsHeadersAndShowsTheSettingsAsPosted(): void
    {
        $input = WebhookInput::fromBody([
            'name'    => ' Zapier ',
            'url'     => ' https://a.example.com ',
            'secret'  => '',
            'headers' => "X-One: 1\r\n X-Two : a: b\n: no name\nno colon",
            'enabled' => 'on',
        ]);

        static::assertSame(
            [
                ['X-One' => '1', 'X-Two' => 'a: b'],
                [
                    'name'    => 'Zapier',
                    'url'     => 'https://a.example.com',
                    'method'  => 'POST',
                    'secret'  => '',
                    'headers' => "X-One: 1\r\n X-Two : a: b\n: no name\nno colon",
                    'enabled' => true,
                ],
                null,
            ],
            [$input->data()['headers'], $input->shown(), $input->data()['secret']],
        );
    }

    #[Test]
    #[DataProvider('methodProvider')]
    public function sendsWithPostOrPut(string $posted, string $method): void
    {
        static::assertSame($method, WebhookInput::fromBody(['method' => $posted])->data()['method']);
    }

    #[Test]
    public function showsANewWebhookSending(): void
    {
        static::assertSame(
            ['name' => '', 'url' => '', 'method' => 'POST', 'secret' => '', 'headers' => '', 'enabled' => true],
            WebhookInput::values(null),
        );
    }

    #[Test]
    public function showsASavedWebhookAndAPostedSecret(): void
    {
        static::assertSame(
            [
                [
                    'name'    => 'Zapier',
                    'url'     => 'https://a.example.com',
                    'method'  => 'PUT',
                    'secret'  => $this->key,
                    'headers' => 'X: 1',
                    'enabled' => false,
                ],
                $this->key,
            ],
            [
                WebhookInput::values(new WebhookDefinition(
                    id: 1,
                    name: 'Zapier',
                    url: 'https://a.example.com',
                    method: 'PUT',
                    secret: $this->key,
                    headers: ['X' => '1'],
                    enabled: false,
                )),
                WebhookInput::fromBody(['secret' => $this->key])->shown()['secret'],
            ],
        );
    }
}
