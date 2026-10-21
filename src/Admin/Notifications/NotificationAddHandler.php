<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Override;

/**
 * Adds a notification to the form: its details, then, once they check out,
 * the notification saved with an empty layout, on its Details screen.
 */
final class NotificationAddHandler extends AbstractFormHandler
{
    public const string NAME = 'notification-add';

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        if (! $form->posts()) {
            return NotificationDetails::screen($form, null, NotificationInput::values(null));
        }

        $input    = NotificationInput::fromBody($form->body());
        $problems = $input->problems();
        if ([] !== $problems) {
            return NotificationDetails::screen($form, null, $input->shown(), $problems);
        }

        $id = $form->forms->repository->createNotification($form->id, $input->data());

        return NotificationOf::saved($form, NotificationHandler::NAME, $id, 'Notification added.');
    }
}
