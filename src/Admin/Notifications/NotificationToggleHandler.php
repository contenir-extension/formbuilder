<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Override;

/**
 * Turns one of the form's notifications on or off, posted from the
 * Notifications tab's menu, and goes back there saying which.
 */
final class NotificationToggleHandler extends AbstractFormHandler
{
    public const string NAME = 'notification-toggle';

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $notification = NotificationOf::named($form);
        $id           = $notification?->id;
        if (null === $notification || null === $id || ! $form->posts()) {
            return NotificationOf::list($form);
        }

        $form->forms->repository->updateNotification($id, ['enabled' => ! $notification->enabled]);

        return NotificationOf::list($form)->withMessage(
            'success',
            $notification->enabled ? 'Notification disabled.' : 'Notification enabled.',
        );
    }
}
