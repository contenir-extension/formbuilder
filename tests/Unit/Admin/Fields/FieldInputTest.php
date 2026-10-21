<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Fields;

use Contenir\Extension\Formbuilder\Admin\Fields\FieldInput;
use Contenir\Extension\Formbuilder\Admin\Fields\FieldTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_key_exists;

#[Group('unit')]
final class FieldInputTest extends TestCase
{
    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function nameProvider(): array
    {
        $refused = [
            'name' => 'Field ID must start with a letter and contain only lowercase letters, numbers, dashes and underscores.',
        ];

        return [
            'a slug'                => ['email', []],
            'made a slug'           => ['Work Email!', []],
            'starting with a digit' => ['9 lives', $refused],
            'nothing left'          => ['  !! ', $refused],
        ];
    }

    /**
     * @param array<string, string> $problems
     */
    #[Test]
    #[DataProvider('nameProvider')]
    public function checksTheFieldId(string $posted, array $problems): void
    {
        static::assertSame($problems, FieldInput::fromBody(['name' => $posted], FieldTypes::selectable())->problems());
    }

    #[Test]
    public function keepsAFieldsPlaceUnlessASortIsPosted(): void
    {
        $types = FieldTypes::selectable();

        static::assertSame(
            [false, 3],
            [
                array_key_exists('sort', FieldInput::fromBody([], $types)->data),
                FieldInput::fromBody(['sort' => '3'], $types)->data['sort'] ?? null,
            ],
        );
    }

    #[Test]
    public function keepsTheStoredTypeWhateverIsPosted(): void
    {
        $input = FieldInput::fromBody(['type' => 'number', 'name' => 'age'], FieldTypes::selectable())->withType(
            'text',
        );

        static::assertSame(['text', 'age', []], [$input->data['type'], $input->data['name'], $input->problems()]);
    }

    #[Test]
    public function leavesAnotherTypesHtmlOptionAsPosted(): void
    {
        $options = FieldInput::fromBody([
            'type'    => 'text',
            'options' => ['html' => '<script>x</script>'],
        ], FieldTypes::selectable())->data['options'];

        static::assertSame(['html' => '<script>x</script>'], $options);
    }

    #[Test]
    public function makesAnUnknownTypeTextAndJoinsSeveralDefaults(): void
    {
        $data = FieldInput::fromBody([
            'type'          => 'hologram',
            'default_value' => [' a ', '', 'b'],
        ], FieldTypes::selectable())->data;

        static::assertSame(['text', 'a,b', null], [$data['type'], $data['default_value'], $data['conditional']]);
    }

    #[Test]
    public function makesTheIdASlugWithoutDashesAtItsEnds(): void
    {
        static::assertSame(
            'work-email',
            FieldInput::fromBody(['name' => '!Work Email!'], FieldTypes::selectable())->data['name'],
        );
    }

    #[Test]
    public function readsAFieldsSettingsAsTheBuiltInModuleDid(): void
    {
        $input = FieldInput::fromBody([
            'type'          => 'select',
            'name'          => ' Favourite Colour ',
            'label'         => ' Colour ',
            'show_label'    => '0',
            'description'   => '',
            'placeholder'   => '  ',
            'default_value' => ' red ',
            'required'      => '1',
            'options'       => [
                'choice_value' => ['red', '', 'blue'],
                'choice_label' => ['Red', 'Ignored', ''],
                'size'         => '',
            ],
            'validators'    => [['type' => 'regex', 'options' => ['pattern' => '/x/'], 'message' => ' ']],
            'filters'       => [' trim ', ''],
            'conditional'   => ['combinator' => 'all', 'conditions' => [['field' => 'email', 'op' => 'is_not_empty']]],
        ], FieldTypes::selectable());

        static::assertSame(
            [
                'type'          => 'select',
                'name'          => 'favourite-colour',
                'label'         => 'Colour',
                'show_label'    => false,
                'description'   => null,
                'placeholder'   => null,
                'default_value' => 'red',
                'required'      => true,
                'options'       => [
                    'choices' => [['value' => 'red', 'label' => 'Red'], ['value' => 'blue', 'label' => 'blue']],
                ],
                'validators'    => [['type' => 'regex', 'options' => ['pattern' => '/x/'], 'message' => null]],
                'filters'       => ['trim'],
                'conditional'   => ['show_when' => ['all' => [['field' => 'email', 'op' => 'is_not_empty']]]],
            ],
            $input->data,
        );
    }

    #[Test]
    public function sanitisesAContentFieldsHtml(): void
    {
        $options = FieldInput::fromBody([
            'type'    => 'content',
            'options' => ['html' => '<p>Hi</p><script>alert(1)</script>'],
        ], FieldTypes::selectable())->data['options'];

        static::assertSame(['html' => '<p>Hi</p>'], $options);
    }

    #[Test]
    public function storesNoDefaultWhenNoneIsTicked(): void
    {
        static::assertNull(
            FieldInput::fromBody(['default_value' => ['', ' ']], FieldTypes::selectable())->data['default_value'],
        );
    }
}
