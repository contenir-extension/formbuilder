<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;

/**
 * A notification's details screen, new or saved, under the form's tabs with
 * Notifications current: the details in the record forms' markup, each
 * address and the subject offering the token picker.
 */
final class NotificationDetails
{
    /**
     * @param ?int                                                                                                            $notification The notification's id; null for a new one
     * @param array{name: string, to_address: string, from_address: string, reply_to: string, subject: string, enabled: bool} $values
     * @param array<string, string>                                                                                           $errors
     */
    public static function screen(
        FormRequest $form,
        ?int $notification,
        array $values,
        array $errors = [],
    ): HandlerResult {
        $title = '' === $values['name'] ? 'Notification' : $values['name'];
        if (null === $notification) {
            $title = 'New notification';
        }

        return HandlerResult::template(
            'forms/notification',
            $title,
            [
                'form'         => $form->form,
                'notification' => $notification,
                'item'         => $values,
                'errors'       => $errors,
                'tags'         => TokenVocabulary::of($form->form),
            ],
            FormTabs::of($form->id, FormTabs::NOTIFICATIONS),
            FormTabs::parent($form->id, $form->form->title, FormTabs::NOTIFICATIONS),
        );
    }
}
