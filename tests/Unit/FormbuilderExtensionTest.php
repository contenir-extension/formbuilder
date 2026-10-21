<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit;

use Contenir\Extension\Api\Handler\Privilege;
use Contenir\Extension\Api\Migration\Target;
use Contenir\Extension\Api\Registration;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Formbuilder\Admin\FormsHandler;
use Contenir\Extension\Formbuilder\FormbuilderExtension;
use Contenir\FormBuilder\ConfigProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function dirname;
use function is_dir;
use function is_file;

#[Group('unit')]
final class FormbuilderExtensionTest extends TestCase
{
    #[Test]
    public function drawsTheFormsHomeFromItsHandler(): void
    {
        $handler = $this->registration()->handlers[FormsHandler::NAME] ?? null;

        static::assertSame([FormsHandler::class, null], [$handler?->class, $handler?->privilege]);
    }

    #[Test]
    public function guardsTheFieldsEditorWithUpdate(): void
    {
        $handlers = $this->registration()->handlers;
        $editor   = [];
        foreach ([
            'fields',
            'section-insert',
            'group-delete',
            'row-reorder',
            'field-add',
            'field-edit',
            'field-reorder',
        ] as $name) {
            $editor[$name] = $handlers[$name]?->privilege;
        }

        static::assertSame(
            [
                'fields'         => Privilege::Update,
                'section-insert' => Privilege::Update,
                'group-delete'   => Privilege::Update,
                'row-reorder'    => Privilege::Update,
                'field-add'      => Privilege::Update,
                'field-edit'     => Privilege::Update,
                'field-reorder'  => Privilege::Update,
            ],
            $editor,
        );
    }

    #[Test]
    public function isAModuleWithItsAreaAndTemplates(): void
    {
        $module = $this->registration()->module;

        static::assertSame(
            [dirname(__DIR__, levels: 2) . '/config/admin.xml', true, dirname(__DIR__, levels: 2) . '/templates', true],
            [
                $module->adminXml,
                is_file((string) $module->adminXml),
                $module->templates,
                is_dir((string) $module->templates),
            ],
        );
    }

    #[Test]
    public function keepsItsSigningKeyThroughItsOwnMigration(): void
    {
        $migrations = $this->registration()->migrations[1] ?? null;

        static::assertSame(
            [dirname(__DIR__, levels: 2) . '/src/Schema', FormbuilderExtension::SCHEMA_NAMESPACE, Target::Site],
            [$migrations?->path, $migrations?->namespace, $migrations?->target],
        );
    }

    #[Test]
    public function keepsTheFormTablesThroughTheLibrarysMigration(): void
    {
        $migrations = $this->registration()->migrations[0] ?? null;

        static::assertSame(
            [ConfigProvider::schemaPath(), ConfigProvider::SCHEMA_NAMESPACE, Target::Site],
            [$migrations?->path, $migrations?->namespace, $migrations?->target],
        );
    }

    #[Test]
    public function letsARoleViewingFormsTestThemInThePreview(): void
    {
        static::assertSame(Privilege::View, $this->registration()->handlers['preview']?->privilege);
    }

    #[Test]
    public function shipsTheEditorsScriptsBuiltInItsAssets(): void
    {
        $module  = $this->registration()->module;
        $missing = [];
        foreach ($module->components ?? [] as $component) {
            if (is_file("{$module?->assets}/{$component->script}")) {
                continue;
            }

            $missing[] = $component->script;
        }

        static::assertSame(
            [dirname(__DIR__, levels: 2) . '/public', 12, [], true],
            [
                $module?->assets,
                count($module->components ?? []),
                $missing,
                is_file(dirname(__DIR__, levels: 2) . '/public/css/formbuilder.css'),
            ],
        );
    }

    private function registration(): Registration
    {
        $registry = new Registry(FormbuilderExtension::HANDLE);
        (new FormbuilderExtension())->register($registry);

        return $registry->registration();
    }
}
