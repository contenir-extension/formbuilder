<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Group;
use Contenir\Extension\Formbuilder\Admin\SettingsForm;
use Contenir\Extension\Formbuilder\Template\FormTemplateRegistry;
use Contenir\FormBuilder\Definition\FormDefinition;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_combine;
use function array_filter;
use function array_map;
use function array_values;

#[TestGroup('unit')]
final class SettingsFormTest extends TestCase
{
    #[Test]
    public function describesTheSettingsInTheBuiltInModulesThreeCards(): void
    {
        static::assertSame(
            [
                'Form details'        => [['title', 'slug'], ['description']],
                'Display & behaviour' => [
                    ['status', 'layout_mode'],
                    ['submit_label', 'submit_alignment', 'retention_days'],
                ],
                'After submission'    => [
                    ['success_mode'],
                    ['success_redirect_url'],
                    ['success_title'],
                    ['success_message'],
                ],
            ],
            array_combine(
                array_map(static fn(Group $group): string => $group->legend, SettingsForm::of([])->groups),
                array_map(static fn(Group $group): array => $group->rows, SettingsForm::of([])->groups),
            ),
        );
    }

    #[Test]
    public function requiresANameAndAnId(): void
    {
        static::assertSame(
            ['title', 'slug'],
            array_values(array_map(
                static fn(Field $field): string => $field->name,
                array_filter(SettingsForm::of([])->fields, static fn(Field $field): bool => $field->required),
            )),
        );
    }

    #[Test]
    public function showsAFormsSettings(): void
    {
        $form = new FormDefinition(
            id: 3,
            slug: 'contact',
            title: 'Contact',
            description: 'Say hello.',
            layoutMode: 'stepped',
            submitLabel: 'Send',
            submitAlignment: 'right',
            settings: ['success' => ['mode' => 'redirect_url', 'redirect_url' => '/thanks', 'title' => 7]],
            retentionDays: 90,
            status: 'inactive',
        );

        static::assertSame(
            [
                'title'                => 'Contact',
                'slug'                 => 'contact',
                'description'          => 'Say hello.',
                'status'               => 'inactive',
                'layout_mode'          => 'stepped',
                'submit_label'         => 'Send',
                'submit_alignment'     => 'right',
                'retention_days'       => 90,
                'success_mode'         => 'redirect_url',
                'success_redirect_url' => '/thanks',
                'success_title'        => '7',
                'success_message'      => null,
            ],
            SettingsForm::values($form),
        );
    }

    #[Test]
    public function showsAFormWithNoAfterSubmissionSettingsReloadingThePage(): void
    {
        static::assertSame(
            'redirect_referrer',
            SettingsForm::values(new FormDefinition(
                id: 3,
                slug: 'a',
                title: 'A',
            ))['success_mode'],
        );
    }

    #[Test]
    public function showsTheValuesItIsGiven(): void
    {
        $form = SettingsForm::of(['title' => 'Contact', 'retention_days' => 30, 'status' => 'inactive']);

        static::assertSame(
            ['Contact', 30, 'inactive', null],
            [
                $form->field('title')?->default,
                $form->field('retention_days')?->default,
                $form->field('status')?->default,
                $form->field('slug')?->default,
            ],
        );
    }

    #[Test]
    public function startsANewFormBlankOrFromItsTemplate(): void
    {
        $template = (new FormTemplateRegistry())->get('contact');

        static::assertSame(
            [
                ['', '', null, 'active', 'single', 'Submit', 'left', null, 'redirect_referrer'],
                ['Contact us', 'contact', $template?->description],
            ],
            [
                array_values(SettingsForm::blank(null)),
                [
                    SettingsForm::blank($template)['title'],
                    SettingsForm::blank($template)['slug'],
                    SettingsForm::blank($template)['description'],
                ],
            ],
        );
    }
}
