<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Preview;

use Contenir\Extension\Api\Mail\Message;
use Contenir\Extension\Api\Testing\MemoryMailer;
use Contenir\Extension\Formbuilder\Admin\Preview\HostMail;
use Contenir\FormBuilder\Delivery\EmailMessage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class HostMailTest extends TestCase
{
    #[Test]
    public function sendsANotificationThroughTheSitesMail(): void
    {
        $mailer = new MemoryMailer();
        (new HostMail($mailer))->send(new EmailMessage(
            form: 'contact',
            name: 'Team',
            to: ['team@example.com', 'boss@example.com'],
            subject: 'Test: New enquiry',
            html: '<p>Hi</p>',
            text: 'Hi',
            from: 'site@example.com',
            replyTo: 'ada@example.com',
        ));

        static::assertEquals(
            [new Message(
                ['team@example.com', 'boss@example.com'],
                'Test: New enquiry',
                '<p>Hi</p>',
                'Hi',
                from: 'site@example.com',
                replyTo: 'ada@example.com',
            )],
            $mailer->sent(),
        );
    }
}
