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
 * Deletes one of the form's webhooks, posted from the Integrations tab,
 * which asks first, and goes back there. A webhook of another form is left
 * as it is.
 */
final class WebhookDeleteHandler extends AbstractFormHandler
{
    public const string NAME = 'webhook-delete';

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        $result = HandlerResult::redirect(Urls::handler(FormTabs::INTEGRATIONS, ['form' => $form->id]));
        $id     = $form->number('webhook');
        foreach ($form->form->webhooks as $webhook) {
            if ($webhook->id !== $id || ! $form->posts()) {
                continue;
            }

            $form->forms->repository->deleteWebhook($id);

            return $result->withMessage('success', 'Webhook deleted.');
        }

        return $result;
    }
}
