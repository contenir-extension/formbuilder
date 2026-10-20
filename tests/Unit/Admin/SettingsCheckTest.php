<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin;

use Contenir\Extension\Formbuilder\Admin\SettingsCheck;
use Contenir\Extension\Formbuilder\Admin\SettingsInput;
use Contenir\FormBuilder\Repository\FormRepositoryInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;

#[Group('unit')]
final class SettingsCheckTest extends TestCase
{
    /**
     * @return array<string, array{array<string, string>, list<string>}>
     */
    public static function problemProvider(): array
    {
        $ok = ['title' => 'Contact', 'slug' => 'contact'];

        return [
            'nothing'                       => [$ok, []],
            'no title or slug'              => [
                [],
                ['Title is required.', 'Slug must be lowercase letters, numbers and hyphens.'],
            ],
            'a slug starting with a dash'   => [['title' => 'A', 'slug' => '-a'], []],
            'a slug another form has'       => [['title' => 'A', 'slug' => 'taken'], ['Slug is already in use.']],
            'retention of none'             => [
                [...$ok, 'retention_days' => '0'],
                ['Retention must be a positive number of days, or empty.'],
            ],
            'retention of a day'            => [[...$ok, 'retention_days' => '1'], []],
            'a redirect with no URL'        => [
                [...$ok, 'success_mode' => 'redirect_url'],
                ['A URL is required when redirecting to a custom URL.'],
            ],
            'a redirect elsewhere'          => [
                [...$ok, 'success_mode' => 'redirect_url', 'success_redirect_url' => 'ftp://x'],
                ['URL must be http(s) or a site-relative path (start with /).'],
            ],
            'a redirect with a URL inside'  => [
                [...$ok, 'success_mode' => 'redirect_url', 'success_redirect_url' => 'see https://x.test'],
                ['URL must be http(s) or a site-relative path (start with /).'],
            ],
            'no title and no message'       => [
                ['slug' => 'a', 'success_mode' => 'inline_message'],
                ['Title is required.', 'A confirmation message is required when showing an inline message.'],
            ],
            'a redirect on the site'        => [
                [...$ok, 'success_mode' => 'redirect_url', 'success_redirect_url' => '/thanks'],
                [],
            ],
            'a redirect to HTTPS'           => [
                [...$ok, 'success_mode' => 'redirect_url', 'success_redirect_url' => 'HTTPS://x.test'],
                [],
            ],
            'a URL kept for another choice' => [[...$ok, 'success_redirect_url' => 'ftp://x'], []],
            'a message with none'           => [
                [...$ok, 'success_mode' => 'inline_message'],
                ['A confirmation message is required when showing an inline message.'],
            ],
            'a message with one'            => [
                [...$ok, 'success_mode' => 'inline_message', 'success_message' => 'Thanks'],
                [],
            ],
        ];
    }

    /**
     * @param array<string, string> $posted
     */
    private static function input(array $posted): SettingsInput
    {
        return SettingsInput::fromRequest((new ServerRequest('POST', '/'))->withParsedBody($posted));
    }

    #[Test]
    public function letsAFormKeepItsOwnSlug(): void
    {
        $forms = $this->createMock(FormRepositoryInterface::class);
        $forms->expects($this->once())->method('isSlugTaken')->with('contact', 3)->willReturn(false);

        static::assertSame(
            [],
            (new SettingsCheck($forms))->problems(self::input(['title' => 'C', 'slug' => 'contact']), 3),
        );
    }

    /**
     * @param array<string, string> $posted
     * @param list<string>          $problems
     */
    #[Test]
    #[DataProvider('problemProvider')]
    public function saysWhatStopsTheSettingsSaving(array $posted, array $problems): void
    {
        static::assertSame($problems, $this->check(['taken'])->problems(self::input($posted), null));
    }

    /**
     * @param list<string> $taken
     */
    private function check(array $taken): SettingsCheck
    {
        $forms = $this->createStub(FormRepositoryInterface::class);
        $forms->method('isSlugTaken')
            ->willReturnCallback(static fn(string $slug): bool => in_array($slug, $taken, strict: true));

        return new SettingsCheck($forms);
    }
}
