<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Fields;

use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Formbuilder\Admin\Fields\FieldAddHandler;
use Contenir\Extension\Formbuilder\Admin\Fields\FieldEditHandler;
use Contenir\Extension\Formbuilder\Tests\TestAsset\FormShape;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;

/**
 * Adding a field to a row and editing one: its settings in the modal for
 * the editor's script, or on a page of their own without it; saved once
 * they check out, back on the fields screen.
 */
#[Group('integration')]
#[Group('handler')]
final class FieldHandlersTest extends TestCase
{
    use FieldsEditorTrait;

    /**
     * @return array<string, array{string, string}>
     */
    public static function typeProvider(): array
    {
        return [
            'a type it knows'         => ['email', 'email'],
            'a type it doesn\'t know' => ['hologram', 'text'],
            'no type'                 => ['', 'text'],
        ];
    }

    #[Test]
    public function addsNothingToAnotherFormsRow(): void
    {
        $ids    = $this->layOut();
        $other  = $this->layOut('Newsletter', 'newsletter');
        $result = $this->page(
            FieldAddHandler::class,
            'POST',
            ['form' => $ids['form'], 'row' => $other['rows'][0]],
            [
                'name' => 'extra',
            ],
        );

        static::assertSame(
            [ResultKind::Redirect, [], ['name', 'email', 'message']],
            [$result->kind, $result->messages, FormShape::names($this->form($other['form']))],
        );
    }

    #[Test]
    public function addsTheFieldAtTheRowsEndAndSendsTheScriptToTheFieldsScreen(): void
    {
        $ids    = $this->layOut();
        $result = $this->answer(
            FieldAddHandler::class,
            'POST',
            ['form' => $ids['form'], 'row' => $ids['rows'][1]],
            [
                'type'       => 'email',
                'name'       => 'Work Email',
                'label'      => 'Work email',
                'show_label' => '1',
                'required'   => '1',
            ],
        );
        $field = $this->form($ids['form'])?->sections[0]->groups[0]->rows[1]->fields[1] ?? null;

        static::assertSame(
            [
                ['ok' => true, 'redirect' => "/admin/extension/formbuilder/fields?form={$ids['form']}"],
                [['level' => 'success', 'text' => 'Field added.']],
                ['email', 'work-email', 'Work email', true, true],
            ],
            [
                $result->json,
                $result->messages,
                [$field?->type, $field?->name, $field?->label, $field?->showLabel, $field?->required],
            ],
        );
    }

    #[Test]
    public function editsNothingOfAnotherForm(): void
    {
        $ids    = $this->layOut();
        $other  = $this->layOut('Newsletter', 'newsletter');
        $result = $this->answer(
            FieldEditHandler::class,
            'POST',
            ['form' => $ids['form'], 'field' => $other['fields'][0]],
            [
                'name' => 'taken',
            ],
        );

        static::assertSame([404, ['name', 'email', 'message']], [
            $result->status,
            FormShape::names($this->form($other['form'])),
        ]);
    }

    #[Test]
    public function editsTheFieldKeepingItsTypeAndPlace(): void
    {
        $ids    = $this->layOut();
        $result = $this->page(
            FieldEditHandler::class,
            'POST',
            ['form' => $ids['form'], 'field' => $ids['fields'][1]],
            [
                'type'  => 'number',
                'name'  => 'contact_email',
                'label' => 'Contact email',
            ],
        );
        $field = $this->form($ids['form'])?->sections[0]->groups[0]->rows[0]->fields[1] ?? null;

        static::assertSame(
            [
                ResultKind::Redirect,
                [['level' => 'success', 'text' => 'Field saved.']],
                ['email', 'contact-email', 'Contact email'],
                ['name', 'contact-email', 'message'],
            ],
            [
                $result->kind,
                $result->messages,
                [$field?->type, $field?->name, $field?->label],
                FormShape::names($this->form($ids['form'])),
            ],
        );
    }

    /**
     * @param string $asked The type the request names
     * @param string $shown The type the settings are for
     */
    #[Test]
    #[DataProvider('typeProvider')]
    public function opensANewFieldsSettingsForTheTypeNamed(string $asked, string $shown): void
    {
        $ids    = $this->layOut();
        $result = $this->answer(FieldAddHandler::class, 'GET', [
            'form' => $ids['form'],
            'row'  => $ids['rows'][0],
            'type' => $asked,
        ]);

        static::assertSame(
            [$shown, true],
            [
                $result->fragment->values['field']['type'] ?? null,
                str_contains(
                    $this->drawn($result),
                    "action=\"/admin/extension/formbuilder/field-add?form={$ids['form']}&amp;row={$ids['rows'][0]}\"",
                ),
            ],
        );
    }

    #[Test]
    public function opensASavedFieldsSettingsInTheModal(): void
    {
        $ids    = $this->layOut();
        $result = $this->answer(FieldEditHandler::class, 'GET', ['form' => $ids['form'], 'field' => $ids['fields'][1]]);
        $html   = $this->drawn($result);

        static::assertSame(
            [ResultKind::Fragment, 'forms/fields/modal', 200, true, true, true, false],
            [
                $result->kind,
                $result->fragment?->name,
                $result->status,
                str_contains(
                    $html,
                    "action=\"/admin/extension/formbuilder/field-edit?form={$ids['form']}&amp;field={$ids['fields'][1]}\"",
                ),
                str_contains($html, 'class="input text--mono" value="email"'),
                str_contains($html, '<option value="name">Name</option>'),
                str_contains($html, '<option value="email">'),
            ],
        );
    }

    #[Test]
    public function showsTheSettingsOnAPageOfTheirOwnWithoutTheScript(): void
    {
        $ids    = $this->layOut();
        $result = $this->page(
            FieldAddHandler::class,
            'POST',
            ['form' => $ids['form'], 'row' => $ids['rows'][0]],
            [
                'name' => '',
            ],
        );
        $html = $this->drawn($result);

        static::assertSame(
            [ResultKind::Template, 'New field', true, true, true],
            [
                $result->kind,
                $result->template?->title,
                str_contains($html, 'Check the highlighted fields'),
                str_contains(
                    $html,
                    "<a href=\"/admin/extension/formbuilder/fields?form={$ids['form']}\" aria-current=\"page\">Fields</a>",
                ),
                str_contains($html, '<span>Create field</span>'),
            ],
        );
    }

    #[Test]
    public function showsWhatStopsAFieldSavingInTheModal(): void
    {
        $ids    = $this->layOut();
        $result = $this->answer(
            FieldAddHandler::class,
            'POST',
            ['form' => $ids['form'], 'row' => $ids['rows'][0]],
            [
                'type'  => 'text',
                'name'  => '9 lives',
                'label' => 'Lives',
            ],
        );

        static::assertSame(
            [ResultKind::Fragment, 422, true, ['name', 'email', 'message']],
            [
                $result->kind,
                $result->status,
                str_contains($this->drawn($result), 'Field ID must start with a letter'),
                FormShape::names($this->form($ids['form'])),
            ],
        );
    }

    #[Test]
    public function showsWhatStopsAnEditSavingAndKeepsTheField(): void
    {
        $ids    = $this->layOut();
        $result = $this->answer(
            FieldEditHandler::class,
            'POST',
            ['form' => $ids['form'], 'field' => $ids['fields'][0]],
            [
                'name' => '',
            ],
        );

        static::assertSame(
            [422, 'forms/fields/modal', ['name', 'email', 'message']],
            [$result->status, $result->fragment?->name, FormShape::names($this->form($ids['form']))],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }
}
