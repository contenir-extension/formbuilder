<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Webhooks;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\AbstractFormHandler;
use Contenir\Extension\Formbuilder\Admin\FormRequest;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Override;

/**
 * A form's Integrations tab: its webhooks, with where and how each sends,
 * whether it signs its requests and whether it is on, each to edit or
 * delete, and Add webhook.
 */
final class WebhooksHandler extends AbstractFormHandler
{
    public const string NAME = FormTabs::INTEGRATIONS;

    #[Override]
    protected function answer(FormRequest $form): HandlerResult
    {
        return HandlerResult::template(
            'forms/webhooks',
            $form->form->title,
            ['form' => $form->form],
            FormTabs::of($form->id, self::NAME),
        );
    }
}
