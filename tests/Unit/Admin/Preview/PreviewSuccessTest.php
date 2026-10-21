<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Preview;

use Contenir\Extension\Formbuilder\Admin\Preview\PreviewSuccess;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Service\TokenReplacer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PreviewSuccessTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, array{string, ?string, ?string, ?string}}>
     */
    public static function settingsProvider(): array
    {
        return [
            'nothing set'        => [[], ['redirect_referrer', null, null, null]],
            'an unknown mode'    => [['mode' => 'teleport'], ['redirect_referrer', null, null, null]],
            'a mode not as text' => [['mode' => 3, 'title' => 4], ['redirect_referrer', null, null, null]],
            'a URL with tokens'  => [
                ['mode' => 'redirect_url', 'redirect_url' => 'https://example.com/thanks?name={field:name}'],
                ['redirect_url', 'https://example.com/thanks?name=Ada%20L', null, null],
            ],
            'an empty URL'       => [
                ['mode' => 'redirect_url', 'redirect_url' => ''],
                ['redirect_url', null, null, null],
            ],
            'a message in place' => [
                ['mode' => 'inline_message', 'title' => 'Thanks {field:name}', 'message' => '<p>Entry {entry:id}</p>'],
                ['inline_message', null, 'Thanks Ada L', '<p>Entry 0</p>'],
            ],
        ];
    }

    #[Test]
    public function readsNoSettingsThatAreNotAList(): void
    {
        $form = new FormDefinition(
            id: 1,
            slug: 'contact',
            title: 'Contact',
            settings: ['success' => 'inline_message'],
        );

        static::assertSame('redirect_referrer', PreviewSuccess::of($form, [], new TokenReplacer())->mode);
    }

    /**
     * @param array<string, mixed>                       $success
     * @param array{string, ?string, ?string, ?string} $expected
     */
    #[Test]
    #[DataProvider('settingsProvider')]
    public function saysWhatTheLiveFormWouldDoNext(array $success, array $expected): void
    {
        $form = new FormDefinition(
            id: 1,
            slug: 'contact',
            title: 'Contact',
            settings: ['success' => $success],
        );
        $result = PreviewSuccess::of($form, ['name' => 'Ada L'], new TokenReplacer());

        static::assertSame($expected, [$result->mode, $result->redirectUrl, $result->title, $result->message]);
    }
}
