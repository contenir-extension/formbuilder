<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Override;

/**
 * A form's Notifications tab: each notification with whom it goes to, its
 * subject and whether it sends, and a menu to edit it, turn it on or off,
 * or delete it; and Add notification.
 */
final class NotificationsHandler extends AbstractFormHandler
{
    public const string NAME = FormTabs::NOTIFICATIONS;

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        return HandlerResult::template(
            'forms/notifications',
            $form->form->title,
            ['form' => $form->form],
            FormTabs::of($form->id, self::NAME),
        );
    }
}
