<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Override;

/**
 * Deletes one of the form's notifications, posted from the Notifications
 * tab's menu, and goes back there. One of another form is left as it is.
 */
final class NotificationDeleteHandler extends AbstractFormHandler
{
    public const string NAME = 'notification-delete';

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $id = NotificationOf::named($form)?->id;
        if (null === $id || ! $form->posts()) {
            return NotificationOf::list($form);
        }

        $form->forms->repository->deleteNotification($id);

        return NotificationOf::list($form)->withMessage('success', 'Notification deleted.');
    }
}
