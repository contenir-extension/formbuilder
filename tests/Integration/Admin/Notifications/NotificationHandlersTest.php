<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Notifications;

use Contenir\Extension\Api\Handler\ParentPage;
use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Api\Settings\SiteSettingsInterface;
use Contenir\Extension\Api\Testing\MemorySiteSettings;
use Contenir\Extension\Formbuilder\Admin\Notifications\NotificationAddHandler;
use Contenir\Extension\Formbuilder\Admin\Notifications\NotificationDeleteHandler;
use Contenir\Extension\Formbuilder\Admin\Notifications\NotificationHandler;
use Contenir\Extension\Formbuilder\Admin\Notifications\NotificationLayoutHandler;
use Contenir\Extension\Formbuilder\Admin\Notifications\NotificationPreviewHandler;
use Contenir\Extension\Formbuilder\Admin\Notifications\NotificationsHandler;
use Contenir\Extension\Formbuilder\Admin\Notifications\NotificationToggleHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use Contenir\FormBuilder\Definition\NotificationDefinition;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;
use function str_contains;

/**
 * A form's Notifications tab and the screens about each notification, as
 * the built-in module had them.
 */
#[Group('integration')]
#[Group('handler')]
final class NotificationHandlersTest extends TestCase
{
    use FieldsEditorTrait;

    /**
     * @return array<string, array{string, string, list<string>, int}>
     */
    public static function grantProvider(): array
    {
        return [
            'the list, to a role viewing'    => ['GET', 'notifications', ['forms:view'], 200],
            'adding, by a role updating'     => ['GET', 'notification-add', ['forms:update'], 403],
            'the details, to a role viewing' => ['GET', 'notification', ['forms:view'], 403],
            'the layout, to a role updating' => ['GET', 'notification-layout', ['forms:update'], 302],
            'previewing, by a role viewing'  => ['POST', 'notification-preview', ['forms:view'], 200],
            'turning off, by a role viewing' => ['POST', 'notification-toggle', ['forms:view'], 403],
            'deleting, by a role updating'   => ['POST', 'notification-delete', ['forms:update'], 403],
        ];
    }

    /**
     * @return array<string, array{string, string, array<string, string>}>
     */
    public static function problemProvider(): array
    {
        return [
            'no recipient' => ['', 'Hello', ['to_address' => 'A recipient address is required.']],
            'no subject'   => ['a@example.com', ' ', ['subject' => 'Subject is required.']],
            'neither'      => [
                '',
                '',
                ['to_address' => 'A recipient address is required.', 'subject' => 'Subject is required.'],
            ],
        ];
    }

    #[Test]
    public function addsANotificationAndOpensItsDetails(): void
    {
        $ids    = $this->layOut();
        $result = $this->page(
            NotificationAddHandler::class,
            'POST',
            ['form' => $ids['form']],
            [
                'name'         => ' ',
                'to_address'   => ' team@example.com ',
                'from_address' => '',
                'reply_to'     => '{field:email}',
                'subject'      => 'New {form:title}',
                'enabled'      => '1',
            ],
        );
        $saved = $this->notifications($ids['form'])[0] ?? null;

        static::assertSame(
            [
                ResultKind::Redirect,
                "/admin/extension/formbuilder/notification?form={$ids['form']}&notification={$saved?->id}",
                [['level' => 'success', 'text' => 'Notification added.']],
                ['Notification', 'submit', 'team@example.com', null, '{field:email}', 'New {form:title}', null, true],
            ],
            [
                $result->kind,
                $result->url,
                $result->messages,
                [
                    $saved?->name,
                    $saved?->trigger,
                    $saved?->toAddress,
                    $saved?->fromAddress,
                    $saved?->replyTo,
                    $saved?->subject,
                    $saved?->bodyTemplate,
                    $saved?->enabled,
                ],
            ],
        );
    }

    #[Test]
    public function deletesOnlyThisFormsNotificationWhenPosted(): void
    {
        $ids    = $this->layOut();
        $other  = $this->layOut('Newsletter', 'newsletter');
        $mine   = $this->notification($ids['form']);
        $theirs = $this->notification($other['form']);
        $this->page(NotificationDeleteHandler::class, 'GET', ['form' => $ids['form'], 'notification' => $mine]);
        $this->page(NotificationDeleteHandler::class, 'POST', ['form' => $ids['form'], 'notification' => $theirs]);
        $this->page(NotificationToggleHandler::class, 'POST', ['form' => $ids['form'], 'notification' => $theirs]);
        $this->page(NotificationToggleHandler::class, 'GET', ['form' => $ids['form'], 'notification' => $mine]);
        $result = $this->page(NotificationDeleteHandler::class, 'POST', [
            'form'         => $ids['form'],
            'notification' => $mine,
        ]);

        static::assertSame(
            [[['level' => 'success', 'text' => 'Notification deleted.']], [], [false]],
            [
                $result->messages,
                $this->notifications($ids['form']),
                array_map(
                    static fn(NotificationDefinition $n): bool => $n->enabled,
                    $this->notifications($other['form']),
                ),
            ],
        );
    }

    #[Test]
    public function drawsANewNotificationsDetailsWithTheTagPicker(): void
    {
        $ids    = $this->layOut();
        $result = $this->page(NotificationAddHandler::class, 'GET', ['form' => $ids['form']]);
        $html   = $this->drawn($result);

        static::assertSame(
            ['New notification', true, true, true, false, true],
            [
                $result->template?->title,
                str_contains($html, '<template id="token-picker-details">'),
                str_contains($html, 'data-token-insert="{field:email}"'),
                str_contains($html, 'id="ntf-enabled" name="enabled" value="1" class="switch__input" checked'),
                str_contains($html, 'tabs--pills'),
                str_contains($html, '<span>Create notification</span>'),
            ],
        );
    }

    #[Test]
    public function drawsTheLayoutEditorWithItsBodyAndTheToken(): void
    {
        $ids          = $this->layOut();
        $notification = $this->notification($ids['form']);
        $result       = $this->page(NotificationLayoutHandler::class, 'GET', [
            'form'         => $ids['form'],
            'notification' => $notification,
        ]);
        $html = $this->drawn($result);

        static::assertSame(
            ['Team', true, true, true, true],
            [
                $result->template?->title,
                str_contains($html, '&lt;p&gt;Body&lt;/p&gt;</textarea>'),
                str_contains(
                    $html,
                    "data-preview-url=\"/admin/extension/formbuilder/notification-preview?form={$ids['form']}&amp;notification={$notification}\"",
                ),
                str_contains(
                    $html,
                    '<div data-formbuilder-token hidden><input type="hidden" name="token" value="in-memory"></div>',
                ),
                str_contains($html, '<template id="token-picker-layout">'),
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
    public function keepsTheLayoutsWhitespace(): void
    {
        $ids          = $this->layOut();
        $notification = $this->notification($ids['form']);
        $this->page(
            NotificationLayoutHandler::class,
            'POST',
            ['form' => $ids['form'], 'notification' => $notification],
            [
                'body_template' => "  <p>{field:name}</p>\n",
            ],
        );

        static::assertSame("  <p>{field:name}</p>\n", $this->notifications($ids['form'])[0]->bodyTemplate ?? null);
    }

    #[Test]
    public function listsEachNotificationWithItsMenu(): void
    {
        $ids = $this->layOut();
        $this->notification($ids['form']);
        $html = $this->drawn($this->page(NotificationsHandler::class, 'GET', ['form' => $ids['form']]));
        $none = $this->drawn($this->page(NotificationsHandler::class, 'GET', [
            'form' => $this->layOut('Newsletter', 'newsletter')['form'],
        ]));

        static::assertSame(
            [true, true, true, true],
            [
                str_contains(
                    $html,
                    '<form class="card__content card__content--flush" id="notification-actions" method="post" action="">',
                ),
                str_contains($html, '<span class="dropdown__item-text">Turn on</span>'),
                str_contains($html, '<input type="hidden" name="token" value="in-memory">'),
                str_contains($none, 'No notifications'),
            ],
        );
    }

    #[Test]
    public function previewsALinkToAnUploadAsASample(): void
    {
        $ids = $this->layOut();
        $this->repository()->createField($ids['rows'][1], ['type' => 'file', 'name' => 'cv']);
        $html = $this->drawn($this->answer(
            NotificationPreviewHandler::class,
            'POST',
            ['form' => $ids['form']],
            ['body_template' => '<p>{file:cv} {file:name}</p>'],
        ));

        static::assertSame(
            [true, true],
            [
                str_contains($html, '">:///api/extension/formbuilder/file?field=cv&sample=1</span>'),
                str_contains($html, ' {file:name}</p>'),
            ],
        );
    }

    #[Test]
    public function previewsSiteSettingsTagsFromTheSitesSettings(): void
    {
        $this->setUpHost([
            SiteSettingsInterface::class => new MemorySiteSettings(['contact' => ['telephone' => '01234 567890']]),
        ]);
        $ids  = $this->layOut();
        $html = $this->drawn($this->answer(
            NotificationPreviewHandler::class,
            'POST',
            ['form' => $ids['form']],
            ['body_template' => '<p>Call {settings:contact.telephone}</p>'],
        ));

        static::assertStringContainsString('title="{settings:contact.telephone}">01234 567890</span>', $html);
    }

    #[Test]
    public function previewsTheBodyWithSamplesAsAPageOfItsOwn(): void
    {
        $ids    = $this->layOut();
        $result = $this->answer(
            NotificationPreviewHandler::class,
            'POST',
            ['form' => $ids['form']],
            [
                'body_template' => '<p>{field:email} on {entry:date} via {site:admin_url}</p>',
            ],
        );

        static::assertSame(
            [
                ResultKind::Fragment,
                true,
                true,
            ],
            [
                $result->kind,
                str_contains(
                    $this->drawn($result),
                    '<span class="token-replaced" title="{field:email}">alex@example.com</span>',
                ),
                str_contains(
                    $this->drawn($result),
                    '2026-10-20 09:00:00</span> via <span class="token-replaced" title="{site:admin_url}">/admin</span>',
                ),
            ],
        );
    }

    #[Test]
    public function savesTheDetailsAndKeepsTheLayout(): void
    {
        $ids          = $this->layOut();
        $notification = $this->notification($ids['form']);
        $result       = $this->page(
            NotificationHandler::class,
            'POST',
            ['form' => $ids['form'], 'notification' => $notification],
            [
                'name'       => 'Team',
                'to_address' => 'boss@example.com',
                'subject'    => 'Hello',
            ],
        );
        $saved = $this->notifications($ids['form'])[0] ?? null;

        static::assertSame(
            [[['level' => 'success', 'text' => 'Notification saved.']], 'boss@example.com', '<p>Body</p>', false],
            [$result->messages, $saved?->toAddress, $saved?->bodyTemplate, $saved?->enabled],
        );
    }

    #[Test]
    public function savesTheLayoutAsTypedAndKeepsTheDetails(): void
    {
        $ids          = $this->layOut();
        $notification = $this->notification($ids['form']);
        $saved        = $this->page(
            NotificationLayoutHandler::class,
            'POST',
            ['form' => $ids['form'], 'notification' => $notification],
            [
                'body_template' => "  <p>{field:name}</p>\n",
            ],
        );
        $cleared = $this->page(
            NotificationLayoutHandler::class,
            'POST',
            ['form' => $ids['form'], 'notification' => $notification],
            [
                'body_template' => '',
            ],
        );

        $stored = $this->notifications($ids['form'])[0] ?? null;

        static::assertSame(
            [
                [['level' => 'success', 'text' => 'Notification layout saved.']],
                ResultKind::Redirect,
                null,
                'team@example.com',
            ],
            [$saved->messages, $cleared->kind, $stored?->bodyTemplate, $stored?->toAddress],
        );
    }

    #[Test]
    #[DataProvider('problemProvider')]
    public function saysWhatStopsTheDetailsSaving(string $to, string $subject, array $errors): void
    {
        $ids          = $this->layOut();
        $notification = $this->notification($ids['form']);
        $added        = $this->page(
            NotificationAddHandler::class,
            'POST',
            ['form' => $ids['form']],
            ['to_address' => $to, 'subject' => $subject],
        );
        $edited = $this->page(
            NotificationHandler::class,
            'POST',
            ['form' => $ids['form'], 'notification' => $notification],
            [
                'to_address' => $to,
                'subject'    => $subject,
            ],
        );

        static::assertSame(
            [$errors, $errors, true, 1, 'team@example.com'],
            [
                $added->template->values['errors'] ?? null,
                $edited->template->values['errors'] ?? null,
                str_contains($this->drawn($edited), 'Check the highlighted fields'),
                count($this->notifications($ids['form'])),
                $this->notifications($ids['form'])[0]->toAddress ?? null,
            ],
        );
    }

    #[Test]
    public function sendsAFormThatIsGoneBackToTheFormsHome(): void
    {
        static::assertSame(
            ['/admin/extension/formbuilder/forms', 'That form no longer exists.'],
            [
                $this->page(NotificationsHandler::class, 'GET', ['form' => 99])->url,
                $this->page(NotificationsHandler::class, 'GET', ['form' => 99])->messages[0]['text'] ?? null,
            ],
        );
    }

    #[Test]
    public function sendsAnotherFormsNotificationBackToTheList(): void
    {
        $ids    = $this->layOut();
        $other  = $this->layOut('Newsletter', 'newsletter');
        $theirs = $this->notification($other['form']);
        $query  = ['form' => $ids['form'], 'notification' => $theirs];

        static::assertSame(
            [
                "/admin/extension/formbuilder/notifications?form={$ids['form']}",
                "/admin/extension/formbuilder/notifications?form={$ids['form']}",
            ],
            [
                $this->page(NotificationHandler::class, 'GET', $query)->url,
                $this->page(NotificationLayoutHandler::class, 'GET', $query)->url,
            ],
        );
    }

    #[Test]
    public function showsANotificationsPagesUnderItsForm(): void
    {
        $ids          = $this->layOut();
        $notification = $this->repository()->createNotification($ids['form'], [
            'name'       => 'Team',
            'to_address' => 'a@example.com',
            'subject'    => 'New enquiry',
        ]);
        $parent = new ParentPage('Contact', 'notifications', ['form' => $ids['form']]);

        static::assertEquals(
            [$parent, $parent],
            [
                $this->page(NotificationHandler::class, 'GET', [
                    'form'         => $ids['form'],
                    'notification' => $notification,
                ])
                    ->template?->parent,
                $this->page(NotificationLayoutHandler::class, 'GET', [
                    'form'         => $ids['form'],
                    'notification' => $notification,
                ])
                    ->template?->parent,
            ],
        );
    }

    #[Test]
    public function showsASavedNotificationsDetailsUnderItsPills(): void
    {
        $ids          = $this->layOut();
        $notification = $this->notification($ids['form']);
        $result       = $this->page(NotificationHandler::class, 'GET', [
            'form'         => $ids['form'],
            'notification' => $notification,
        ]);
        $html = $this->drawn($result);

        static::assertSame(
            ['Team', true, true, true],
            [
                $result->template?->title,
                str_contains($html, 'tabs--pills'),
                str_contains($html, 'value="team@example.com"'),
                str_contains($html, '<span>Save</span>'),
            ],
        );
    }

    #[Test]
    public function turnsANotificationOffAndOn(): void
    {
        $ids          = $this->layOut();
        $notification = $this->notification($ids['form'], true);
        $off          = $this->page(NotificationToggleHandler::class, 'POST', [
            'form'         => $ids['form'],
            'notification' => $notification,
        ]);
        $wasOff = $this->notifications($ids['form'])[0]->enabled ?? null;
        $on     = $this->page(NotificationToggleHandler::class, 'POST', [
            'form'         => $ids['form'],
            'notification' => $notification,
        ]);

        static::assertSame(
            [
                [['level' => 'success', 'text' => 'Notification disabled.']],
                false,
                [['level' => 'success', 'text' => 'Notification enabled.']],
                true,
                "/admin/extension/formbuilder/notifications?form={$ids['form']}",
            ],
            [$off->messages, $wasOff, $on->messages, $this->notifications($ids['form'])[0]->enabled ?? null, $on->url],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * A notification on the form, its layout set, off unless $enabled.
     */
    private function notification(int $form, bool $enabled = false): int
    {
        return $this->repository()->createNotification($form, [
            'name'          => 'Team',
            'to_address'    => 'team@example.com',
            'subject'       => 'New',
            'body_template' => '<p>Body</p>',
            'enabled'       => $enabled,
        ]);
    }

    /**
     * @return list<NotificationDefinition>
     */
    private function notifications(int $form): array
    {
        return $this->form($form)->notifications ?? [];
    }
}
