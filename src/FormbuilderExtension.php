<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder;

use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Formbuilder\Admin\FormsHandler;
use Contenir\FormBuilder\ConfigProvider;
use Override;

use function dirname;

/**
 * The form builder as a module (plan section 10, decision M2): the Forms
 * area in the navigation rail, with its screens as handlers over
 * contenir/contenir-formbuilder's persistence. Its form_* tables keep their
 * names (P10), created by the library's baseline migration, which leaves a
 * site's existing tables as they are.
 */
final readonly class FormbuilderExtension implements ExtensionInterface
{
    public const string HANDLE = 'formbuilder';

    /**
     * The area its admin.xml declares, which guards its screens as the CMS's
     * built-in Forms area did.
     */
    public const string AREA = 'forms';

    #[Override]
    public function register(Registry $registry): void
    {
        $root = dirname(__DIR__);
        $registry->module("{$root}/config/admin.xml");
        $registry->templates("{$root}/templates");
        $registry->migrations(ConfigProvider::schemaPath(), ConfigProvider::SCHEMA_NAMESPACE);
        $registry->handler(FormsHandler::NAME, FormsHandler::class);
    }
}
