<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Override;

/**
 * A notification's Layout: its email's HTML, edited beside a live preview,
 * saved as typed without touching its details. One of another form, or one that is
 * gone, goes back to the Notifications tab.
 */
final class NotificationLayoutHandler extends AbstractFormHandler
{
    public const string NAME = 'notification-layout';

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $notification = NotificationOf::named($form);
        $id           = $notification?->id;
        if (null === $notification || null === $id) {
            return NotificationOf::list($form);
        }

        if ($form->posts()) {
            $body = $form->text('body_template');
            $form->forms->repository->updateNotification($id, ['body_template' => '' === $body ? null : $body]);

            return NotificationOf::saved($form, self::NAME, $id, 'Notification layout saved.');
        }

        $title = '' === $notification->name ? 'Notification' : $notification->name;

        return HandlerResult::template(
            'forms/notification-layout',
            $title,
            [
                'title'        => $title,
                'form'         => $form->form,
                'notification' => $id,
                'body'         => $notification->bodyTemplate ?? '',
                'tags'         => TokenVocabulary::of($form->form),
            ],
            FormTabs::of($form->id, FormTabs::NOTIFICATIONS),
            FormTabs::parent($form->id, $form->form->title, FormTabs::NOTIFICATIONS),
        );
    }
}
