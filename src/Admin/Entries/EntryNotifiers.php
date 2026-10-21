<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Mail\MailerInterface;
use Contenir\Extension\Formbuilder\Admin\Preview\HostMail;
use Contenir\FormBuilder\Delivery\ImmediateDelivery;
use Contenir\FormBuilder\Registrar\EmailNotificationRegistrar;
use Contenir\FormBuilder\Registrar\WebhookRegistrar;
use Contenir\FormBuilder\Service\TokenReplacer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use SplObserver;

/**
 * The registrars that tell people about an entry when one held as spam is
 * marked complete, as the built-in module wired them: its notification
 * emails, through the site's mail when the host lends it, and its webhooks,
 * each sent at once. Tokens fill in the admin's and the site's address.
 */
final class EntryNotifiers
{
    /**
     * @return list<SplObserver>
     *
     * @throws ContainerExceptionInterface When the host can't give its mail.
     */
    public static function of(ServerRequestInterface $request, ExtensionContextInterface $context): array
    {
        $services = $context->services();
        $mailer   = $services->has(MailerInterface::class) ? $services->get(MailerInterface::class) : null;
        $delivery = new ImmediateDelivery(
            $mailer instanceof MailerInterface ? new HostMail($mailer) : null,
            log: $context->logger(),
        );
        $uri = $request->getUri();

        return [
            new EmailNotificationRegistrar(
                new TokenReplacer([
                    'admin_url' => '/admin',
                    'base_url'  => "{$uri->getScheme()}://{$uri->getAuthority()}",
                ]),
                $delivery,
                $context->logger(),
            ),
            new WebhookRegistrar($context->logger(), delivery: $delivery),
        ];
    }
}
