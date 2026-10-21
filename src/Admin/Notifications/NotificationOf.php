<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Contenir\FormBuilder\Definition\NotificationDefinition;

/**
 * The notification a request names, when it is one of the form's, and
 * where the screens about it go.
 */
final class NotificationOf
{
    /**
     * Back to the form's Notifications tab.
     */
    public static function list(FormRequest $form): HandlerResult
    {
        return HandlerResult::redirect(Urls::handler(FormTabs::NOTIFICATIONS, ['form' => $form->id]));
    }

    public static function named(FormRequest $form): ?NotificationDefinition
    {
        $id = $form->number('notification');
        foreach ($form->form->notifications as $notification) {
            if (null !== $notification->id && $notification->id === $id) {
                return $notification;
            }
        }

        return null;
    }

    /**
     * Back to one of the notification's screens, saying $message.
     */
    public static function saved(FormRequest $form, string $handler, int $notification, string $message): HandlerResult
    {
        return HandlerResult::redirect(Urls::handler($handler, ['form' => $form->id, 'notification' => $notification]))
            ->withMessage('success', $message);
    }
}
