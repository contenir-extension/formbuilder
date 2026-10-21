<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Webhooks;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Override;

/**
 * Adds a webhook to the form: its settings, then, once they check out, the
 * webhook saved and the Integrations tab saying so.
 */
final class WebhookAddHandler extends AbstractFormHandler
{
    public const string NAME = 'webhook-add';

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        if (! $form->posts()) {
            return WebhookForm::screen('New webhook', $form->id, $form->form->title, WebhookInput::values(null));
        }

        $input    = WebhookInput::fromBody($form->body());
        $problems = $input->problems();
        if ([] !== $problems) {
            return WebhookForm::screen(
                'New webhook',
                $form->id,
                $form->form->title,
                $input->shown(),
                $problems,
            );
        }

        $form->forms->repository->createWebhook($form->id, $input->data());

        return HandlerResult::redirect(Urls::handler(FormTabs::INTEGRATIONS, ['form' => $form->id]))
            ->withMessage('success', 'Webhook added.');
    }
}
