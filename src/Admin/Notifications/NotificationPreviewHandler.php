<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Contenir\Extension\Formbuilder\Uploads\FileTokens;
use Contenir\Extension\Formbuilder\Uploads\FileWebhook;
use Contenir\FormBuilder\Service\TokenReplacer;
use Override;

/**
 * The Layout tab's live preview: the posted email HTML filled in with
 * sample values, as a page of its own for the editor's preview frame.
 */
final class NotificationPreviewHandler extends AbstractFormHandler
{
    public const string NAME = 'notification-preview';

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $uri    = $form->request->getUri();
        $base   = "{$uri->getScheme()}://{$uri->getAuthority()}";
        $tokens = new TokenReplacer(['admin_url' => '/admin', 'base_url' => $base]);
        FileTokens::register(
            $tokens,
            $form->form,
            static fn(string $field): string => $base
            . Urls::webhook(FileWebhook::NAME, ['field' => $field, 'sample' => 1]),
        );
        $preview = new NotificationPreview($tokens, $form->context->clock()->now());

        return HandlerResult::fragment('forms/notifications/preview', [
            'body' => $preview->body($form->text('body_template'), $form->form),
        ]);
    }
}
