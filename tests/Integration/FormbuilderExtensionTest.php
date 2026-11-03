<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration;

use Contenir\Extension\Api\Conformance\AbstractAddOnConformanceTestCase;
use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Formbuilder\FormbuilderExtension;
use Contenir\Extension\Formbuilder\Persistence\SiteDatabase;
use Contenir\Extension\Formbuilder\Tests\Trait\FormsDatabaseTrait;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;

use function dirname;

/**
 * The API's own checks for an add-on: it registers cleanly, its boundary
 * file is current, and it has no webhook that would answer an unverified
 * request. The add-on runs over a freshly migrated site database, as the
 * CMS gives it one, so its file links are checked against a real signing key.
 */
#[Group('integration')]
final class FormbuilderExtensionTest extends AbstractAddOnConformanceTestCase
{
    use FormsDatabaseTrait;

    #[Override]
    protected function extension(): ExtensionInterface
    {
        return new FormbuilderExtension();
    }

    #[Override]
    protected function handle(): string
    {
        return FormbuilderExtension::HANDLE;
    }

    #[Override]
    protected function http(): Psr17Factory
    {
        return new Psr17Factory();
    }

    #[Override]
    protected function root(): string
    {
        return dirname(__DIR__, levels: 2);
    }

    /**
     * @return array<string, object>
     */
    #[Override]
    protected function services(): array
    {
        return [SiteDatabase::SERVICE => $this->database];
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }
}
