<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Preview;

use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Api\Handler\Tab;
use Contenir\Extension\Api\Mail\MailerInterface;
use Contenir\Extension\Api\Mail\Message;
use Contenir\Extension\Api\Settings\SiteSettingsInterface;
use Contenir\Extension\Api\Testing\MemoryMailer;
use Contenir\Extension\Api\Testing\MemorySiteSettings;
use Contenir\Extension\Formbuilder\Admin\Preview\PreviewHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function preg_match;
use function str_contains;

/**
 * A form's Preview tab: the form as the site renders it, and a test that
 * runs its checks and sends its notifications as tests, storing nothing.
 */
#[Group('integration')]
#[Group('handler')]
final class PreviewHandlerTest extends TestCase
{
    use FieldsEditorTrait;

    private MemoryMailer $mailer;

    /**
     * @return array<string, array{string, list<string>, int}>
     */
    public static function grantProvider(): array
    {
        return [
            'looking, by a role viewing'   => ['GET', ['forms:view'], 200],
            'testing, by a role viewing'   => ['POST', ['forms:view'], 200],
            'looking, by a role with none' => ['GET', [], 403],
        ];
    }

    /**
     * @return array<string, array{array<string, object>, string}>
     */
    public static function siteSettingsProvider(): array
    {
        return [
            'lent by the host'     => [
                [
                    SiteSettingsInterface::class => new MemorySiteSettings(['contact' => [
                        'telephone' => '01234 567890',
                    ]]),
                ],
                'Test: Call 01234 567890 or ',
            ],
            'not lent by the host' => [[], 'Test: Call {settings:contact.telephone} or {settings:contact.fax}'],
        ];
    }

    #[Test]
    public function drawsTheFormAsTheSiteRendersItWithTheTokenInIt(): void
    {
        $this->setUpHost();
        $ids    = $this->layOut();
        $result = $this->page(PreviewHandler::class, 'GET', ['form' => $ids['form']]);
        $html   = $this->drawn($result);

        static::assertSame(
            [ResultKind::Template, 'forms/preview', ['preview'], true, true, true, false],
            [
                $result->kind,
                $result->template?->name,
                array_values(array_map(
                    static fn(Tab $tab): string => $tab->handler,
                    array_filter($result->template->tabs ?? [], static fn(Tab $tab): bool => $tab->current),
                )),
                str_contains($html, 'Test mode'),
                str_contains($html, 'class="form-preview__field') && ! str_contains($html, 'required and can'),
                (bool) preg_match('/<form\b[^>]*><input type="hidden" name="token" value="in-memory">/', $html),
                str_contains($html, 'Submission valid'),
            ],
        );
    }

    #[Test]
    public function fillsInWhereTheTestCameFromAndTheAdminsAddress(): void
    {
        $this->mailer = new MemoryMailer();
        $this->setUpHost([MailerInterface::class => $this->mailer]);
        $ids = $this->layOut();
        $this->repository()->createNotification($ids['form'], [
            'name'       => 'Team',
            'to_address' => 'team@example.com',
            'subject'    => 'From {entry:ip} at {site:base_url}{site:admin_url}',
        ]);
        (new PreviewHandler())->handle(
            (new ServerRequest(
                'POST',
                "https://example.com/admin/extension/formbuilder/preview?form={$ids['form']}",
                [],
                null,
                '1.1',
                [
                    'REMOTE_ADDR' => '203.0.113.9',
                ],
            ))->withQueryParams(['form' => $ids['form']])
                ->withParsedBody($this->drawnToken($ids['form']) + ['name' => 'Ada', 'email' => 'ada@example.com']),
            $this->context(),
        );

        static::assertSame(
            ['Test: From 203.0.113.9 at https://example.com/admin'],
            array_map(static fn(Message $message): string => $message->subject, $this->mailer->sent()),
        );
    }

    /**
     * @param array<string, object> $services
     */
    #[Test]
    #[DataProvider('siteSettingsProvider')]
    public function fillsSiteSettingsTagsWhenTheHostLendsThem(array $services, string $subject): void
    {
        $this->mailer = new MemoryMailer();
        $this->setUpHost([MailerInterface::class => $this->mailer] + $services);
        $ids = $this->layOut();
        $this->repository()->createNotification($ids['form'], [
            'name'          => 'Team',
            'to_address'    => 'team@example.com',
            'subject'       => 'Call {settings:contact.telephone} or {settings:contact.fax}',
            'body_template' => '<p>{field:message}</p>',
        ]);
        $this->page(
            PreviewHandler::class,
            'POST',
            ['form' => $ids['form']],
            $this->drawnToken($ids['form']) + ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Hello'],
        );

        static::assertSame(
            [$subject],
            array_map(static fn(Message $message): string => $message->subject, $this->mailer->sent()),
        );
    }

    #[Test]
    public function logsANotificationTheSitesMailFails(): void
    {
        $this->setUpHost([MailerInterface::class => MemoryMailer::failing()]);
        $ids = $this->layOut();
        $this->repository()->createNotification($ids['form'], [
            'name'       => 'Team',
            'to_address' => 'team@example.com',
            'subject'    => 'New',
        ]);
        $this->page(
            PreviewHandler::class,
            'POST',
            ['form' => $ids['form']],
            [0 => 'a stray value'] + $this->drawnToken($ids['form']) + ['name' => 'Ada', 'email' => 'ada@example.com'],
        );

        static::assertStringContainsString('The mail server could not be reached', $this->logged());
    }

    #[Test]
    public function logsTheNotificationsItCannotSendWithoutTheSitesMail(): void
    {
        $this->setUpHost();
        $ids = $this->layOut();
        $this->repository()->createNotification($ids['form'], [
            'name'       => 'Team',
            'to_address' => 'team@example.com',
            'subject'    => 'New',
        ]);
        $result = $this->page(
            PreviewHandler::class,
            'POST',
            ['form' => $ids['form']],
            $this->drawnToken($ids['form']) + ['name' => 'Ada', 'email' => 'ada@example.com'],
        );

        static::assertSame(
            [true, true],
            [
                str_contains($this->drawn($result), 'Submission valid'),
                str_contains(
                    $this->logged(),
                    'Form notification "Team" failed for form "contact": no mail transport is configured',
                ),
            ],
        );
    }

    /**
     * @param list<string> $grants
     */
    #[Test]
    #[DataProvider('grantProvider')]
    public function needsViewInTheFormsArea(string $method, array $grants, int $status): void
    {
        $this->setUpHost();
        $ids      = $this->layOut();
        $response = $this->host->handle(
            (new Psr17Factory())->createServerRequest(
                $method,
                "/admin/extension/formbuilder/preview?form={$ids['form']}",
            ),
            $grants,
        );

        static::assertSame($status, $response->getStatusCode());
    }

    #[Test]
    public function sendsAFormThatIsGoneBackToTheFormsHome(): void
    {
        $this->setUpHost();
        $result = $this->page(PreviewHandler::class, 'GET', ['form' => 99]);

        static::assertSame('/admin/extension/formbuilder/forms', $result->url);
    }

    #[Test]
    public function sendsTheNotificationsAsTestsAndStoresNoEntry(): void
    {
        $this->mailer = new MemoryMailer();
        $this->setUpHost([MailerInterface::class => $this->mailer]);
        $ids = $this->layOut();
        $this->repository()->createNotification($ids['form'], [
            'name'          => 'Team',
            'to_address'    => 'team@example.com',
            'subject'       => 'New enquiry from {field:name}',
            'body_template' => '<p>{field:message}</p>',
        ]);
        $result = $this->page(
            PreviewHandler::class,
            'POST',
            ['form' => $ids['form']],
            $this->drawnToken($ids['form'])
                + [
                    'name'    => 'Ada',
                    'email'   => 'ada@example.com',
                    'message' => 'Hello',
                ],
        );

        static::assertSame(
            [true, ['Test: New enquiry from Ada'], [['team@example.com']], 0],
            [
                str_contains($this->drawn($result), 'The live form would reload the page that hosted it.'),
                array_map(static fn(Message $message): string => $message->subject, $this->mailer->sent()),
                array_column($this->mailer->sent(), 'to'),
                $this->entries(),
            ],
        );
    }

    #[Test]
    public function showsWhatStopsATestAndSendsNothing(): void
    {
        $this->mailer = new MemoryMailer();
        $this->setUpHost([MailerInterface::class => $this->mailer]);
        $ids = $this->layOut();
        $this->repository()->createNotification($ids['form'], [
            'name'       => 'Team',
            'to_address' => 'team@example.com',
            'subject'    => 'New',
        ]);
        $html = $this->drawn($this->page(
            PreviewHandler::class,
            'POST',
            ['form' => $ids['form']],
            $this->drawnToken($ids['form']) + ['name' => 'Ada', 'email' => 'nope'],
        ));

        static::assertSame(
            [false, true, []],
            [
                str_contains($html, 'Submission valid'),
                str_contains($html, 'class="form-preview__field'),
                $this->mailer->sent(),
            ],
        );
    }

    /**
     * The form's own anti-forgery field, as the preview draws it for a
     * browser to post back.
     *
     * @return array<string, string>
     */
    private function drawnToken(int $form): array
    {
        preg_match('/name="_csrf" value="([^"]+)"/', $this->drawn($this->page(PreviewHandler::class, 'GET', [
            'form' => $form,
        ])), $match);

        return ['_csrf' => $match[1] ?? ''];
    }

    private function entries(): int
    {
        return (int) ($this->database->query('SELECT COUNT(*) AS n FROM form_entry', [])->current()['n'] ?? -1);
    }

    private function logged(): string
    {
        return implode("\n", array_column($this->host->logs(), 'message'));
    }
}
