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
 * Edits one of the form's webhooks: its settings, then, once they check
 * out, the webhook saved and the Integrations tab saying so. A webhook of
 * another form, or one that is gone, goes back to the Integrations tab.
 */
final class WebhookHandler extends AbstractFormHandler
{
    public const string NAME = 'webhook';

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $list    = Urls::handler(FormTabs::INTEGRATIONS, ['form' => $form->id]);
        $webhook = null;
        foreach ($form->form->webhooks as $candidate) {
            if ($candidate->id !== $form->number('webhook')) {
                continue;
            }

            $webhook = $candidate;
        }

        $id = $webhook?->id;
        if (null === $webhook || null === $id) {
            return HandlerResult::redirect($list);
        }

        if (! $form->posts()) {
            return WebhookForm::screen($webhook->name, $form->id, $form->form->title, WebhookInput::values($webhook));
        }

        $input    = WebhookInput::fromBody($form->body());
        $problems = $input->problems();
        if ([] !== $problems) {
            return WebhookForm::screen(
                $webhook->name,
                $form->id,
                $form->form->title,
                $input->shown(),
                $problems,
            );
        }

        $form->forms->repository->updateWebhook($id, $input->data());

        return HandlerResult::redirect($list)->withMessage('success', 'Webhook saved.');
    }
}
