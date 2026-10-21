<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Fields;

use Contenir\Extension\Formbuilder\Admin\Fields\FieldTypes;
use Contenir\FormBuilder\FieldType\FieldTypeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class FieldTypesTest extends TestCase
{
    #[Test]
    public function givesATypesOwnIcon(): void
    {
        $types = FieldTypes::selectable();

        static::assertNotSame('square', $types->icon('email'));
    }

    #[Test]
    public function namesATypesLabelDecodedAndItsIcon(): void
    {
        $types = FieldTypes::selectable();

        static::assertSame(
            ['Date & time', 'Hologram', 'square', true, false],
            [
                $types->label('datetime'),
                $types->label('hologram'),
                $types->icon('hologram'),
                $types->has('email'),
                $types->has('hologram'),
            ],
        );
    }

    #[Test]
    public function offersTheTypesAnEditorMayPick(): void
    {
        $keys = array_map(static fn(FieldTypeInterface $type): string => $type->key(), FieldTypes::selectable()->all());

        static::assertSame(['text', 'textarea', 'email'], [$keys[0], $keys[1], $keys[2]]);
    }
}
