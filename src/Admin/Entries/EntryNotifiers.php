<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Mail\MailerInterface;
use Contenir\Extension\Formbuilder\Admin\Preview\HostMail;
use Contenir\Extension\Formbuilder\Uploads\EntryUploads;
use Contenir\Extension\Formbuilder\Uploads\FileLinks;
use Contenir\Extension\Formbuilder\Uploads\FileTokens;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Delivery\ImmediateDelivery;
use Contenir\FormBuilder\Registrar\EmailNotificationRegistrar;
use Contenir\FormBuilder\Registrar\WebhookRegistrar;
use Contenir\FormBuilder\Service\TokenReplacer;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use RuntimeException;
use SplObserver;

use function array_key_exists;

/**
 * The registrars that tell people about an entry when one held as spam is
 * marked complete, as the built-in module wired them: its notification
 * emails, through the site's mail when the host lends it, and its webhooks,
 * each sent at once. Tokens fill in the admin's and the site's address, and
 * {file:<field>} a signed link to the entry's file, valid for seven days.
 */
final class EntryNotifiers
{
    /**
     * @param list<array<string, mixed>> $values The entry's values, as the repository read them
     *
     * @return list<SplObserver>
     *
     * @throws ContainerExceptionInterface When the host can't give its mail.
     */
    public static function of(
        ServerRequestInterface $request,
        ExtensionContextInterface $context,
        FormDefinition $form,
        int $entryId,
        array $values,
    ): array {
        $services = $context->services();
        $mailer   = $services->has(MailerInterface::class) ? $services->get(MailerInterface::class) : null;
        $delivery = new ImmediateDelivery(
            $mailer instanceof MailerInterface ? new HostMail($mailer) : null,
            log: $context->logger(),
        );
        $uri     = $request->getUri();
        $base    = "{$uri->getScheme()}://{$uri->getAuthority()}";
        $uploads = EntryUploads::of($form, $values);
        $tokens  = new TokenReplacer(['admin_url' => '/admin', 'base_url' => $base]);
        FileTokens::register(
            $tokens,
            $form,
            /**
             * @throws ContainerExceptionInterface When the host can't give the site's database.
             * @throws LogicException              When the host offers no site database.
             * @throws RandomException             When no key can be made.
             * @throws RuntimeException            When the key can be neither read nor kept.
             */
            static fn(string $field): string => array_key_exists($field, $uploads)
                ? FileLinks::of($context)->link($base, $entryId, $field)
                : '',
        );

        return [
            new EmailNotificationRegistrar($tokens, $delivery, $context->logger()),
            new WebhookRegistrar($context->logger(), delivery: $delivery),
        ];
    }
}
