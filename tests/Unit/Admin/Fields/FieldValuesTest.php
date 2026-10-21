<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Fields;

use Contenir\Extension\Formbuilder\Admin\Fields\FieldValues;
use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\ValidatorDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class FieldValuesTest extends TestCase
{
    #[Test]
    public function showsANewFieldOfTheTypeWithItsLabelShown(): void
    {
        $values = FieldValues::blank('email');

        static::assertSame(['email', '', true, false, []], [
            $values['type'],
            $values['name'],
            $values['show_label'],
            $values['required'],
            $values['validators'],
        ]);
    }

    #[Test]
    public function showsASavedFieldsSettings(): void
    {
        $values = FieldValues::of(new FieldDefinition(
            id: 3,
            type: 'text',
            name: 'nickname',
            label: 'Nickname',
            showLabel: false,
            required: true,
            sort: 2,
            options: ['size' => 'sm'],
            validators: [new ValidatorDefinition('string_length', ['min' => 2], 'Too short')],
            filters: ['trim'],
            conditional: ['show_when' => ['all' => []]],
        ));

        static::assertSame(
            [
                'type'          => 'text',
                'name'          => 'nickname',
                'label'         => 'Nickname',
                'show_label'    => false,
                'description'   => null,
                'placeholder'   => null,
                'default_value' => null,
                'required'      => true,
                'options'       => ['size' => 'sm'],
                'validators'    => [(new ValidatorDefinition('string_length', ['min' => 2], 'Too short'))->toArray()],
                'filters'       => ['trim'],
                'conditional'   => ['show_when' => ['all' => []]],
            ],
            $values,
        );
    }
}
