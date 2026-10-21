<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Form\Group;
use Contenir\Extension\Formbuilder\Template\FormTemplate;
use Contenir\FormBuilder\Definition\FormDefinition;

use function is_array;
use function is_scalar;

/**
 * A form's settings as its Settings and Add form screens describe them: its
 * details, how it displays and behaves, and what happens after someone
 * submits it, in the CMS's built-in module's words and choices.
 */
final class SettingsForm
{
    /**
     * How long a new form keeps its submissions' personal details: twelve
     * months (FRM-3). A form made before this keeps what it had.
     */
    public const int RETENTION_DAYS = 365;

    /**
     * @var array<string, string>
     */
    public const array STATUSES = [
        FormDefinition::STATUS_ACTIVE   => 'Active',
        FormDefinition::STATUS_INACTIVE => 'Inactive',
    ];

    /**
     * @var array<string, string>
     */
    public const array LAYOUTS = [
        FormDefinition::LAYOUT_SINGLE  => 'Single page',
        FormDefinition::LAYOUT_STEPPED => 'Stepped (multi-page)',
    ];

    /**
     * @var array<string, string>
     */
    public const array ALIGNMENTS = ['left' => 'Left', 'right' => 'Right', 'full' => 'Full width'];

    /**
     * @var array<string, string>
     */
    public const array AFTER = [
        FormDefinition::SUCCESS_REDIRECT_REFERRER => 'Reload the same page',
        FormDefinition::SUCCESS_REDIRECT_URL      => 'Redirect to another page',
        FormDefinition::SUCCESS_INLINE_MESSAGE    => 'Show a confirmation message',
    ];

    /**
     * A new form's settings, from the template it starts from if any.
     *
     * @return array<string, scalar|null>
     */
    public static function blank(?FormTemplate $template): array
    {
        return [
            'title'            => $template->titleSuggestion ?? '',
            'slug'             => $template->slugSuggestion ?? '',
            'description'      => $template?->description,
            'status'           => FormDefinition::STATUS_ACTIVE,
            'layout_mode'      => FormDefinition::LAYOUT_SINGLE,
            'submit_label'     => 'Submit',
            'submit_alignment' => 'left',
            'retention_days'   => self::RETENTION_DAYS,
            'success_mode'     => FormDefinition::SUCCESS_REDIRECT_REFERRER,
        ];
    }

    /**
     * The settings as a described form, each field showing $values.
     *
     * @param array<string, scalar|null> $values
     */
    public static function of(array $values): Form
    {
        $value = static fn(string $name): string|int|float|bool|null => $values[$name] ?? null;

        return new Form(
            [
                new Field(
                    'title',
                    'Form name',
                    required: true,
                    default: $value('title'),
                    description: 'What you’ll call this form: shown in the admin list and in email subjects.',
                ),
                new Field(
                    'slug',
                    'Form ID',
                    required: true,
                    default: $value('slug'),
                    description: 'Appears in the form’s web address: lowercase letters, numbers and dashes only. Filled in from the Form name when left empty.',
                ),
                new Field(
                    'description',
                    'Instructions',
                    FieldType::Textarea,
                    default: $value('description'),
                    description: 'Shown above the form to anyone filling it in.',
                ),
                new Field(
                    'status',
                    'Status',
                    FieldType::Select,
                    default: $value('status'),
                    options: self::STATUSES,
                    description: 'Inactive forms are hidden from the website and won’t accept submissions.',
                ),
                new Field(
                    'layout_mode',
                    'Layout mode',
                    FieldType::Select,
                    default: $value('layout_mode'),
                    options: self::LAYOUTS,
                    description: 'Single page shows everything at once. Stepped breaks the form into pages with Next and Previous buttons.',
                ),
                new Field('submit_label', 'Submit button label', default: $value('submit_label')),
                new Field(
                    'submit_alignment',
                    'Submit alignment',
                    FieldType::Select,
                    default: $value('submit_alignment'),
                    options: self::ALIGNMENTS,
                ),
                new Field(
                    'retention_days',
                    'Retention (days)',
                    FieldType::Number,
                    default: $value('retention_days'),
                    description: 'Personal details from submissions older than this many days are removed automatically. Leave empty to keep submissions forever.',
                ),
                new Field(
                    'success_mode',
                    'When the form is submitted…',
                    FieldType::Select,
                    default: $value('success_mode'),
                    options: self::AFTER,
                    description: 'What people see after they submit the form.',
                ),
                new Field(
                    'success_redirect_url',
                    'Redirect URL',
                    default: $value('success_redirect_url'),
                    description: 'For Redirect to another page: absolute (https://…) or site-relative (/…).',
                ),
                new Field(
                    'success_title',
                    'Confirmation title',
                    default: $value('success_title'),
                    description: 'For Show a confirmation message.',
                ),
                new Field(
                    'success_message',
                    'Confirmation message',
                    FieldType::Textarea,
                    default: $value('success_message'),
                    description: 'For Show a confirmation message: shown in place of the form. Tokens like {field:first_name} are replaced with submitted values.',
                ),
            ],
            [
                new Group('details', 'Form details', [['title', 'slug'], 'description']),
                new Group('display', 'Display & behaviour', [
                    ['status', 'layout_mode'],
                    ['submit_label', 'submit_alignment', 'retention_days'],
                ]),
                new Group('after', 'After submission', [
                    'success_mode',
                    'success_redirect_url',
                    'success_title',
                    'success_message',
                ]),
            ],
        );
    }

    /**
     * A form's settings as the screen's fields show them.
     *
     * @return array<string, scalar|null>
     */
    public static function values(FormDefinition $form): array
    {
        $success = is_array($form->settings['success'] ?? null) ? $form->settings['success'] : [];

        return [
            'title'                => $form->title,
            'slug'                 => $form->slug,
            'description'          => $form->description,
            'status'               => $form->status,
            'layout_mode'          => $form->layoutMode,
            'submit_label'         => $form->submitLabel,
            'submit_alignment'     => $form->submitAlignment,
            'retention_days'       => $form->retentionDays,
            'success_mode'         => self::text($success, 'mode') ?? FormDefinition::SUCCESS_REDIRECT_REFERRER,
            'success_redirect_url' => self::text($success, 'redirect_url'),
            'success_title'        => self::text($success, 'title'),
            'success_message'      => self::text($success, 'message'),
        ];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function text(array $values, string $key): ?string
    {
        return is_scalar($values[$key] ?? null) ? (string) $values[$key] : null;
    }
}
