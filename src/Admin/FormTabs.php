<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Handler\ParentPage;
use Contenir\Extension\Api\Handler\Tab;

/**
 * The tabs a form's screens share, as the CMS's built-in Forms module drew
 * them: its entries, fields, preview, notifications, integrations and
 * settings, the one the screen belongs to current.
 */
final class FormTabs
{
    public const string ENTRIES = 'entries';

    public const string FIELDS = 'fields';

    public const string PREVIEW = 'preview';

    public const string NOTIFICATIONS = 'notifications';

    public const string INTEGRATIONS = 'webhooks';

    public const string SETTINGS = 'form';

    /**
     * @var array<string, array{string, string}> Label and icon, by handler
     */
    private const array TABS = [
        self::ENTRIES       => ['Entries', 'table'],
        self::FIELDS        => ['Fields', 'layout-list'],
        self::PREVIEW       => ['Preview', 'eye'],
        self::NOTIFICATIONS => ['Notifications', 'mail'],
        self::INTEGRATIONS  => ['Integrations', 'send'],
        self::SETTINGS      => ['Settings', 'settings'],
    ];

    /**
     * @param string $current The handler of the tab the screen belongs to
     *
     * @return list<Tab>
     */
    public static function of(int $formId, string $current): array
    {
        $tabs = [];
        foreach (self::TABS as $handler => [$label, $icon]) {
            $tabs[] = new Tab($label, $handler, ['form' => $formId], $handler === $current, $icon);
        }

        return $tabs;
    }

    /**
     * The form as the parent page of one of its records, such as a
     * notification, linking to the tab that lists them, so the breadcrumb
     * reads Forms › the form › the record.
     */
    public static function parent(int $formId, string $formTitle, string $tab): ParentPage
    {
        return new ParentPage($formTitle, $tab, ['form' => $formId]);
    }
}
