<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder;

use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\Privilege;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Api\Schedule\Frequency;
use Contenir\Extension\Formbuilder\Admin\AddFormHandler;
use Contenir\Extension\Formbuilder\Admin\Entries;
use Contenir\Extension\Formbuilder\Admin\EntriesRedactHandler;
use Contenir\Extension\Formbuilder\Admin\Fields;
use Contenir\Extension\Formbuilder\Admin\FormHandler;
use Contenir\Extension\Formbuilder\Admin\FormsHandler;
use Contenir\Extension\Formbuilder\Admin\Notifications;
use Contenir\Extension\Formbuilder\Admin\Preview\PreviewHandler;
use Contenir\Extension\Formbuilder\Admin\UploadsMoveHandler;
use Contenir\Extension\Formbuilder\Admin\Webhooks;
use Contenir\Extension\Formbuilder\Retention\RedactExpiredTask;
use Contenir\Extension\Formbuilder\Uploads\FileWebhook;
use Contenir\FormBuilder\ConfigProvider;
use Override;

use function dirname;

/**
 * The form builder as a module (plan section 10, decision M2): the Forms
 * area in the navigation rail, with its screens as handlers over
 * contenir/contenir-formbuilder's persistence. Its form_* tables keep their
 * names (P10), created by the library's baseline migration, which leaves a
 * site's existing tables as they are.
 */
final readonly class FormbuilderExtension implements ExtensionInterface
{
    public const string HANDLE = 'formbuilder';

    /**
     * The namespace of the add-on's own migrations, in src/Schema.
     */
    public const string SCHEMA_NAMESPACE = __NAMESPACE__ . '\\Schema';

    /**
     * The area its admin.xml declares, which guards its screens as the CMS's
     * built-in Forms area did.
     */
    public const string AREA = 'forms';

    /**
     * The dashboard components the form screens use, by the elements they
     * enhance, each a script built into the add-on's assets.
     *
     * @var array<string, string>
     */
    private const array COMPONENTS = [
        '[data-formbuilder-rows]'    => 'js/FieldsEditor.js',
        '[data-formbuilder-inline]'  => 'js/FormbuilderInlineForm.js',
        '[data-formbuilder-delete]'  => 'js/FormbuilderDelete.js',
        '[data-editable-legend]'     => 'js/EditableLegend.js',
        '[data-field-modal]'         => 'js/FieldFormModal.js',
        '[data-field-form]'          => 'js/FieldForm.js',
        '[data-conditional-editor]'  => 'js/ConditionalEditor.js',
        '[data-identifier]'          => 'js/DataIdentifier.js',
        '[data-form-stepper]'        => 'js/FormStepper.js',
        '[data-form-conditional]'    => 'js/FormConditional.js',
        '[data-notification-layout]' => 'js/NotificationLayout.js',
        '[data-token-insert]'        => 'js/TokenInsert.js',
    ];

    /**
     * A form's entries, notifications and webhooks screens, with the
     * privilege each needs as the built-in module's did; null where reading
     * needs View and posting Update.
     *
     * @var array<string, array{class-string<HandlerInterface>, ?Privilege}>
     */
    private const array SCREENS = [
        Entries\EntriesHandler::NAME                   => [Entries\EntriesHandler::class, null],
        Entries\EntryHandler::NAME                     => [Entries\EntryHandler::class, null],
        Entries\EntryStatusHandler::NAME               => [Entries\EntryStatusHandler::class, Privilege::Update],
        Entries\EntryDeleteHandler::NAME               => [Entries\EntryDeleteHandler::class, Privilege::Delete],
        Entries\EntriesExportHandler::NAME             => [Entries\EntriesExportHandler::class, Privilege::Export],
        Entries\EntryFileHandler::NAME                 => [Entries\EntryFileHandler::class, null],
        Notifications\NotificationsHandler::NAME       => [Notifications\NotificationsHandler::class, null],
        Notifications\NotificationAddHandler::NAME     => [Notifications\NotificationAddHandler::class, Privilege::Add],
        Notifications\NotificationHandler::NAME        => [Notifications\NotificationHandler::class, Privilege::Update],
        Notifications\NotificationLayoutHandler::NAME  => [
            Notifications\NotificationLayoutHandler::class,
            Privilege::Update,
        ],
        Notifications\NotificationPreviewHandler::NAME => [
            Notifications\NotificationPreviewHandler::class,
            Privilege::View,
        ],
        Notifications\NotificationToggleHandler::NAME  => [
            Notifications\NotificationToggleHandler::class,
            Privilege::Update,
        ],
        Notifications\NotificationDeleteHandler::NAME  => [
            Notifications\NotificationDeleteHandler::class,
            Privilege::Delete,
        ],
        Webhooks\WebhooksHandler::NAME                 => [Webhooks\WebhooksHandler::class, null],
        Webhooks\WebhookAddHandler::NAME               => [Webhooks\WebhookAddHandler::class, Privilege::Add],
        Webhooks\WebhookHandler::NAME                  => [Webhooks\WebhookHandler::class, Privilege::Update],
        Webhooks\WebhookDeleteHandler::NAME            => [Webhooks\WebhookDeleteHandler::class, Privilege::Delete],
    ];

    /**
     * The fields editor's handlers, each needing Update in the Forms area, as
     * the built-in module's fields screen did.
     *
     * @var array<string, class-string<HandlerInterface>>
     */
    private const array EDITOR = [
        Fields\FieldsHandler::NAME        => Fields\FieldsHandler::class,
        Fields\SectionInsertHandler::NAME => Fields\SectionInsertHandler::class,
        Fields\SectionDeleteHandler::NAME => Fields\SectionDeleteHandler::class,
        Fields\SectionLegendHandler::NAME => Fields\SectionLegendHandler::class,
        Fields\GroupInsertHandler::NAME   => Fields\GroupInsertHandler::class,
        Fields\GroupDeleteHandler::NAME   => Fields\GroupDeleteHandler::class,
        Fields\GroupLegendHandler::NAME   => Fields\GroupLegendHandler::class,
        Fields\RowInsertHandler::NAME     => Fields\RowInsertHandler::class,
        Fields\RowDeleteHandler::NAME     => Fields\RowDeleteHandler::class,
        Fields\RowReorderHandler::NAME    => Fields\RowReorderHandler::class,
        Fields\FieldAddHandler::NAME      => Fields\FieldAddHandler::class,
        Fields\FieldEditHandler::NAME     => Fields\FieldEditHandler::class,
        Fields\FieldDeleteHandler::NAME   => Fields\FieldDeleteHandler::class,
        Fields\FieldReorderHandler::NAME  => Fields\FieldReorderHandler::class,
    ];

    #[Override]
    public function register(Registry $registry): void
    {
        $root = dirname(__DIR__);
        $registry->module("{$root}/config/admin.xml");
        $registry->templates("{$root}/templates");
        $registry->migrations(ConfigProvider::schemaPath(), ConfigProvider::SCHEMA_NAMESPACE);
        $registry->migrations(__DIR__ . '/Schema', self::SCHEMA_NAMESPACE);
        $registry->handler(FormsHandler::NAME, FormsHandler::class);
        $registry->handler(AddFormHandler::NAME, AddFormHandler::class, Privilege::Add);
        $registry->handler(FormHandler::NAME, FormHandler::class);
        $registry->handler(PreviewHandler::NAME, PreviewHandler::class, Privilege::View);
        $registry->handler(UploadsMoveHandler::NAME, UploadsMoveHandler::class, Privilege::Update);
        $registry->handler(EntriesRedactHandler::NAME, EntriesRedactHandler::class, Privilege::Delete);
        $registry->schedule(RedactExpiredTask::NAME, RedactExpiredTask::class, Frequency::cron(RedactExpiredTask::AT));
        $registry->webhook(FileWebhook::NAME, FileWebhook::class);

        $registry->assets("{$root}/public");
        foreach (self::COMPONENTS as $selector => $script) {
            $registry->component($selector, $script);
        }

        foreach (self::EDITOR as $name => $class) {
            $registry->handler($name, $class, Privilege::Update);
        }

        foreach (self::SCREENS as $name => [$class, $privilege]) {
            $registry->handler($name, $class, $privilege);
        }
    }
}
