<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Webhooks;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\Screen;
use Contenir\Extension\Formbuilder\Admin\FormTabs;

/**
 * A webhook's settings as its Add and Edit screens describe them, in the
 * built-in module's words, under the form's tabs with Integrations current
 * and under the form in the breadcrumb.
 */
final class WebhookForm
{
    /**
     * The screen showing $values, with what stopped the last save.
     *
     * @param array<string, scalar|null> $values
     * @param list<string>               $problems
     */
    public static function screen(
        string $title,
        int $form,
        string $formTitle,
        array $values,
        array $problems = [],
    ): HandlerResult {
        $result = HandlerResult::screen(new Screen(
            $title,
            'Each submission is sent to the URL as JSON.',
            self::of($values),
            tabs: FormTabs::of($form, FormTabs::INTEGRATIONS),
            parent: FormTabs::parent($form, $formTitle, FormTabs::INTEGRATIONS),
        ));
        foreach ($problems as $problem) {
            $result = $result->withMessage('danger', $problem);
        }

        return $result;
    }

    /**
     * @param array<string, scalar|null> $values
     */
    private static function of(array $values): Form
    {
        $value = static fn(string $name): string|int|float|bool|null => $values[$name] ?? null;

        return new Form([
            new Field(
                'name',
                'Name',
                default: $value('name'),
                description: 'A label to help you remember which system this sends to.',
                attributes: ['placeholder' => 'Zapier leads'],
            ),
            new Field('enabled', 'Send when the form is submitted', FieldType::Checkbox, default: $value('enabled')),
            new Field(
                'url',
                'Target URL',
                FieldType::Url,
                required: true,
                default: $value('url'),
                description: 'Where each submission is sent as JSON. It must start with http:// or https://.',
                attributes: ['placeholder' => 'https://hooks.example.com/forms/abc123'],
            ),
            new Field('method', 'Method', FieldType::Select, default: $value('method'), options: WebhookInput::METHODS),
            new Field(
                'secret',
                'Signing secret',
                default: $value('secret'),
                description: 'When set, every request carries an X-Contenir-Signature: sha256=<hex> header: the HMAC-SHA256 of the raw body with this secret, for the receiver to check.',
                attributes: ['placeholder' => 'Optional'],
            ),
            new Field(
                'headers',
                'Extra headers',
                FieldType::Textarea,
                default: $value('headers'),
                description: 'One header per line, as Name: value. Leave empty for none.',
                attributes: ['rows' => 4, 'placeholder' => "X-Custom-Header: value\nAuthorization: Bearer ..."],
            ),
        ]);
    }
}
