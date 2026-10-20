<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit;

use Contenir\Extension\Api\Migration\Target;
use Contenir\Extension\Api\Registration;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Formbuilder\Admin\FormsHandler;
use Contenir\Extension\Formbuilder\FormbuilderExtension;
use Contenir\FormBuilder\ConfigProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function keepsTheFormTablesThroughTheLibrarysMigration(): void
    {
        $migrations = $this->registration()->migrations[0] ?? null;

        static::assertSame(
            [ConfigProvider::schemaPath(), ConfigProvider::SCHEMA_NAMESPACE, Target::Site],
            [$migrations?->path, $migrations?->namespace, $migrations?->target],
        );
    }

    private function registration(): Registration
    {
        $registry = new Registry(FormbuilderExtension::HANDLE);
        (new FormbuilderExtension())->register($registry);

        return $registry->registration();
    }
}
