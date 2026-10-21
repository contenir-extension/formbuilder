<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Override;

/**
 * A notification's Details: its details, saved once they check out,
 * leaving its layout as it is. One of another form, or one that is gone,
 * goes back to the Notifications tab.
 */
final class NotificationHandler extends AbstractFormHandler
{
    public const string NAME = 'notification';

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $notification = NotificationOf::named($form);
        $id           = $notification?->id;
        if (null === $id) {
            return NotificationOf::list($form);
        }

        if (! $form->posts()) {
            return NotificationDetails::screen($form, $id, NotificationInput::values($notification));
        }

        $input    = NotificationInput::fromBody($form->body());
        $problems = $input->problems();
        if ([] !== $problems) {
            return NotificationDetails::screen($form, $id, $input->shown(), $problems);
        }

        $form->forms->repository->updateNotification($id, $input->data());

        return NotificationOf::saved($form, self::NAME, $id, 'Notification saved.');
    }
}
