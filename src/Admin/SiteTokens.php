<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Settings\SiteSettingsInterface;
use Contenir\FormBuilder\Service\TokenReplacer;
use Psr\Container\ContainerExceptionInterface;

/**
 * The {settings:<key>} merge tag: one of the site's settings by its dotted
 * key, such as contact.telephone, read from the settings the host lends, as
 * the built-in module's notifications filled it; empty when that setting
 * isn't set. Without lent settings the tag is left as it is.
 */
final class SiteTokens
{
    /**
     * @throws ContainerExceptionInterface When the host can't give its settings.
     */
    public static function register(TokenReplacer $tokens, ExtensionContextInterface $context): void
    {
        $services = $context->services();
        $settings = $services->has(SiteSettingsInterface::class) ? $services->get(SiteSettingsInterface::class) : null;
        if ($settings instanceof SiteSettingsInterface) {
            $tokens->register('settings', static fn(string $key): string => $settings->get($key) ?? '');
        }
    }
}
