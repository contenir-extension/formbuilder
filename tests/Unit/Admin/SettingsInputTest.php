<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin;

use Contenir\Extension\Formbuilder\Admin\SettingsInput;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SettingsInputTest extends TestCase
{
    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function slugProvider(): array
    {
        return [
            'from the title'        => [['title' => 'Book a Tour (2026)'], 'book-a-tour-2026'],
            'its own'               => [['title' => 'Tour', 'slug' => ' Tours/Bookings '], 'tours-bookings'],
            'nothing to make it of' => [['title' => '!!!'], ''],
        ];
    }

    /**
     * @param array<string, mixed> $posted
     */
    private static function input(array $posted): SettingsInput
    {
        return SettingsInput::fromRequest((new ServerRequest('POST', '/'))->withParsedBody($posted));
    }

    #[Test]
    public function fillsInTheDefaultsForWhatWasLeftOutOrUnknown(): void
    {
        static::assertSame(
            [
                'slug'             => 'enquiry-form',
                'description'      => null,
                'layout_mode'      => 'single',
                'submit_label'     => 'Submit',
                'submit_alignment' => 'left',
                'retention_days'   => null,
                'status'           => 'active',
                'mode'             => 'redirect_referrer',
            ],
            (static fn(array $data): array => [
                'slug'             => $data['slug'],
                'description'      => $data['description'],
                'layout_mode'      => $data['layout_mode'],
                'submit_label'     => $data['submit_label'],
                'submit_alignment' => $data['submit_alignment'],
                'retention_days'   => $data['retention_days'],
                'status'           => $data['status'],
                'mode'             => $data['settings']['success']['mode'],
            ])(
                self::input([
                    'title'            => 'Enquiry Form!',
                    'layout_mode'      => 'grid',
                    'submit_alignment' => 'centre',
                    'status'           => 'deleted',
                    'success_mode'     => 'confetti',
                    'retention_days'   => 'forever',
                    'submit_label'     => [],
                ])->data(),
            ),
        );
    }

    /**
     * @param array<string, string> $posted
     */
    #[Test]
    #[DataProvider('slugProvider')]
    public function makesTheSlugFromTheTitleWhenLeftEmpty(array $posted, string $slug): void
    {
        static::assertSame($slug, self::input($posted)->slug);
    }

    #[Test]
    public function readsNothingFromARequestWithNoBody(): void
    {
        static::assertSame(['', ''], [
            SettingsInput::fromRequest(new ServerRequest('POST', '/'))->title,
            SettingsInput::fromRequest(new ServerRequest('POST', '/'))->slug,
        ]);
    }

    #[Test]
    public function savesWhatWasPostedAsTheRepositoryStoresIt(): void
    {
        static::assertSame(
            [
                'slug'             => 'contact-us',
                'title'            => 'Contact us',
                'description'      => 'Say hello.',
                'layout_mode'      => 'stepped',
                'submit_label'     => 'Send',
                'submit_alignment' => 'full',
                'retention_days'   => 365,
                'status'           => 'inactive',
                'settings'         => ['success' => [
                    'mode'         => 'inline_message',
                    'redirect_url' => null,
                    'title'        => 'Thanks',
                    'message'      => 'We will be in touch.',
                ]],
            ],
            self::input([
                'title'                => ' Contact us ',
                'slug'                 => 'contact-us',
                'description'          => 'Say hello.',
                'layout_mode'          => 'stepped',
                'submit_label'         => 'Send',
                'submit_alignment'     => 'full',
                'retention_days'       => '365',
                'status'               => 'inactive',
                'success_mode'         => 'inline_message',
                'success_redirect_url' => '',
                'success_title'        => 'Thanks',
                'success_message'      => 'We will be in touch.',
            ])->data(),
        );
    }

    #[Test]
    public function showsWhatWasPostedWhenDrawnAgain(): void
    {
        static::assertSame(
            [
                'title'                => 'Contact',
                'slug'                 => 'contact',
                'description'          => null,
                'status'               => 'active',
                'layout_mode'          => 'single',
                'submit_label'         => 'Submit',
                'submit_alignment'     => 'left',
                'retention_days'       => 30,
                'success_mode'         => 'redirect_url',
                'success_redirect_url' => '/thanks',
                'success_title'        => null,
                'success_message'      => null,
            ],
            self::input([
                'title'                => 'Contact',
                'retention_days'       => '30',
                'success_mode'         => 'redirect_url',
                'success_redirect_url' => '/thanks',
            ])->values(),
        );
    }
}
