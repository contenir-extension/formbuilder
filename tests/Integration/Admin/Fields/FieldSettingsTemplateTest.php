<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Fields;

use Contenir\Extension\Formbuilder\Admin\Fields\FieldAddHandler;
use Contenir\Extension\Formbuilder\Admin\Fields\FieldEditHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;

/**
 * A field's settings as the modal draws them for each type an editor may
 * pick: only what the type supports, and a saved field's settings back in
 * their controls.
 */
#[Group('integration')]
final class FieldSettingsTemplateTest extends TestCase
{
    use FieldsEditorTrait;

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function typeProvider(): array
    {
        return [
            'text, with a placeholder'        => ['text', 'name="placeholder"', true],
            'textarea, with its rows'         => ['textarea', 'name="options[rows]"', true],
            'email, with its rules'           => ['email', 'data-validator-rows', true],
            'url'                             => ['url', 'name="label"', true],
            'tel'                             => ['tel', 'name="label"', true],
            'number, with its step'           => ['number', 'name="options[step]"', true],
            'date, with its limits'           => ['date', '<p-datetime name="options[min]"', true],
            'datetime'                        => ['datetime', 'name="label"', true],
            'time'                            => ['time', 'name="label"', true],
            'select, with one default'        => ['select', 'type="radio" name="default_value"', true],
            'multiselect, with many defaults' => ['multiselect', 'name="default_value[]"', true],
            'radio'                           => ['radio', 'name="options[choice_value][]"', true],
            'checkbox'                        => ['checkbox', 'name="label"', true],
            'multicheckbox'                   => ['multicheckbox', 'name="default_value[]"', true],
            'file, with its types'            => ['file', 'name="options[accept]"', true],
            'hidden, without a placeholder'   => ['hidden', 'name="placeholder"', false],
            'content, with its HTML'          => ['content', 'name="options[html]"', true],
        ];
    }

    #[Test]
    public function drawsASavedFieldsSettingsBackInTheirControls(): void
    {
        $ids     = $this->layOut();
        $choices = $this->repository()->createField($ids['rows'][1], [
            'type'          => 'multiselect',
            'name'          => 'topics',
            'default_value' => 'tea,cake',
            'options'       => [
                'choices' => [['value' => 'tea', 'label' => 'Tea'], ['value' => 'cake', 'label' => 'Cake & co']],
            ],
        ]);
        $rules = $this->repository()->createField($ids['rows'][1], [
            'type'        => 'text',
            'name'        => 'nickname',
            'validators'  => [['type' => 'string_length', 'options' => ['min' => 2], 'message' => 'Too short']],
            'conditional' => [
                'show_when' => [
                    'any' => [
                        ['field' => 'email', 'op' => 'is_not_empty'],
                        ['field' => 'name', 'op' => 'equals', 'value' => 'Ada'],
                    ],
                ],
            ],
        ]);
        $html =
            $this->drawn($this->answer(FieldEditHandler::class, 'GET', ['form' => $ids['form'], 'field' => $choices]))
            . $this->drawn($this->answer(FieldEditHandler::class, 'GET', ['form' => $ids['form'], 'field' => $rules]));

        static::assertSame(
            [true, true, true, true, true, true],
            [
                str_contains($html, 'value="Cake &amp; co"'),
                str_contains(
                    $html,
                    'name="default_value[]" value="cake" class="checkbox__input" data-default-row checked',
                ),
                str_contains(
                    $html,
                    'name="validators[0][options][min]" placeholder="Minimum" aria-label="Minimum" class="input" value="2"',
                ),
                str_contains(
                    $html,
                    'name="validators[0][message]" placeholder="Custom error message (optional)" aria-label="Custom error message" class="input" value="Too short"',
                ),
                str_contains($html, '<option value="any" selected>Or</option>'),
                str_contains($html, 'name="conditional[conditions][1][value]" class="input" value="Ada"'),
            ],
        );
    }

    #[Test]
    #[DataProvider('typeProvider')]
    public function drawsWhatTheTypeSupports(string $type, string $control, bool $shown): void
    {
        $ids = $this->layOut();
        $this->repository()->createField($ids['rows'][1], [
            'type'  => 'date',
            'name'  => 'arrival',
            'label' => 'Arrival',
        ]);
        $html = $this->drawn($this->answer(FieldAddHandler::class, 'GET', [
            'form' => $ids['form'],
            'row'  => $ids['rows'][0],
            'type' => $type,
        ]));

        static::assertSame([$shown, true], [
            str_contains($html, $control),
            str_contains($html, "name=\"type\" value=\"{$type}\""),
        ]);
    }

    #[Test]
    public function saysAConditionNeedsAnotherFieldWhenThereIsNone(): void
    {
        $form = $this->addForm('Empty', 'empty');
        $row  = $this->repository()->createRow(
            $this->repository()->createGroup($this->repository()->createSection($form, ['key' => 'main']), []),
        );
        $field = $this->repository()->createField($row, ['type' => 'text', 'name' => 'only']);
        $html  = $this->drawn($this->answer(FieldEditHandler::class, 'GET', ['form' => $form, 'field' => $field]));

        static::assertSame(
            [true, false],
            [
                str_contains($html, 'Add other fields to this form first'),
                str_contains($html, 'data-conditional-editor'),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }
}
