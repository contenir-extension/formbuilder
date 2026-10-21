<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Preview;

use Contenir\Extension\Api\Exception\MailFailedException;
use Contenir\Extension\Api\Mail\MailerInterface;
use Contenir\Extension\Api\Mail\Message;
use Contenir\FormBuilder\Delivery\EmailMessage;
use Contenir\FormBuilder\Delivery\MailTransportInterface;
use Override;

/**
 * The site's mail, as the host lends it, carrying the form builder's
 * notification emails.
 */
final readonly class HostMail implements MailTransportInterface
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    /**
     * @throws MailFailedException When the host couldn't send it.
     */
    #[Override]
    public function send(EmailMessage $message): void
    {
        $this->mailer->send(new Message(
            $message->to,
            $message->subject,
            $message->html,
            $message->text,
            from: $message->from,
            replyTo: $message->replyTo,
        ));
    }
}
