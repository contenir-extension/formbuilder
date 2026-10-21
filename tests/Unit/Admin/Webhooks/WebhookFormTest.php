<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Webhooks;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Formbuilder\Admin\Webhooks\WebhookForm;
use Contenir\Extension\Formbuilder\Admin\Webhooks\WebhookInput;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class WebhookFormTest extends TestCase
{
    #[Test]
    public function describesAWebhooksSettingsInTheBuiltInModulesWords(): void
    {
        $form = WebhookForm::screen('New webhook', 3, 'Contact', WebhookInput::values(null))->screen?->form;

        static::assertSame(
            [
                ['name', 'Name', FieldType::Text->value, false, '', ['placeholder' => 'Zapier leads'], []],
                ['enabled', 'Send when the form is submitted', FieldType::Checkbox->value, false, true, [], []],
                [
                    'url',
                    'Target URL',
                    FieldType::Url->value,
                    true,
                    '',
                    ['placeholder' => 'https://hooks.example.com/forms/abc123'],
                    [],
                ],
                ['method', 'Method', FieldType::Select->value, false, 'POST', [], ['POST' => 'POST', 'PUT' => 'PUT']],
                ['secret', 'Signing secret', FieldType::Text->value, false, '', ['placeholder' => 'Optional'], []],
                [
                    'headers',
                    'Extra headers',
                    FieldType::Textarea->value,
                    false,
                    '',
                    ['rows' => 4, 'placeholder' => "X-Custom-Header: value\nAuthorization: Bearer ..."],
                    [],
                ],
            ],
            array_map(
                static fn(Field $field): array => [
                    $field->name,
                    $field->label,
                    $field->type,
                    $field->required,
                    $field->default,
                    $field->attributes,
                    $field->options,
                ],
                $form->fields ?? [],
            ),
        );
    }

    #[Test]
    public function explainsEachSettingThatNeedsIt(): void
    {
        $form = WebhookForm::screen('New webhook', 3, 'Contact', WebhookInput::values(null))->screen?->form;

        static::assertSame(
            [
                'A label to help you remember which system this sends to.',
                '',
                'Where each submission is sent as JSON. It must start with http:// or https://.',
                '',
                'When set, every request carries an X-Contenir-Signature: sha256=<hex> header: the HMAC-SHA256 of the raw body with this secret, for the receiver to check.',
                'One header per line, as Name: value. Leave empty for none.',
            ],
            array_map(static fn(Field $field): string => $field->description, $form->fields ?? []),
        );
    }
}
