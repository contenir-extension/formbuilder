<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Preview;

use Contenir\FormBuilder\Delivery\EmailMessage;
use Contenir\FormBuilder\Delivery\MailTransportInterface;
use Override;
use RuntimeException;
use Throwable;

/**
 * A test's mail, counting what it sent and what failed so the preview can
 * say so. A failure is passed on, so the delivery still logs why; without
 * mail from the host, each notification fails.
 */
final class CountedMail implements MailTransportInterface
{
    public int $sent = 0;

    public int $failed = 0;

    public function __construct(
        private readonly ?MailTransportInterface $mail,
    ) {}

    /**
     * @throws RuntimeException When the host lends no mail.
     * @throws Throwable        When the host couldn't send it.
     */
    #[Override]
    public function send(EmailMessage $message): void
    {
        try {
            if (null === $this->mail) {
                throw new RuntimeException('this site lends add-ons no mail');
            }

            $this->mail->send($message);
        } catch (Throwable $failure) {
            ++$this->failed;

            throw $failure;
        }

        ++$this->sent;
    }

    /**
     * What a person reads about the test's notifications.
     */
    public function summary(): string
    {
        $count = static fn(int $n): string => 1 === $n ? '1 notification' : "{$n} notifications";

        return match (true) {
            0 === $this->sent && 0 === $this->failed => 'No notification went out for this submission.',
            0 === $this->failed => "Sent {$count($this->sent)} with Test: subjects.",
            0 === $this->sent => "{$count($this->failed)} failed to send; the log says why.",
            default => "Sent {$count($this->sent)} with Test: subjects; {$this->failed} failed, and the log says why.",
        };
    }
}
