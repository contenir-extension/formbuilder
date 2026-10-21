<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Preview;

use Contenir\Extension\Formbuilder\Admin\Preview\CountedMail;
use Contenir\FormBuilder\Delivery\EmailMessage;
use Contenir\FormBuilder\Delivery\MailTransportInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('unit')]
final class CountedMailTest extends TestCase
{
    /**
     * @return array<string, array{int, int, string}>
     */
    public static function summaryProvider(): array
    {
        return [
            'nothing'         => [0, 0, 'No notification went out for this submission.'],
            'one sent'        => [1, 0, 'Sent 1 notification with Test: subjects.'],
            'two sent'        => [2, 0, 'Sent 2 notifications with Test: subjects.'],
            'one failed'      => [0, 1, '1 notification failed to send; the log says why.'],
            'two failed'      => [0, 2, '2 notifications failed to send; the log says why.'],
            'sent and failed' => [2, 1, 'Sent 2 notifications with Test: subjects; 1 failed, and the log says why.'],
        ];
    }

    private static function message(): EmailMessage
    {
        return new EmailMessage('contact', 'Team', ['team@example.com'], 'Test: New', '<p>Hi</p>', 'Hi');
    }

    #[Test]
    public function countsAndPassesOnAFailure(): void
    {
        $transport = static::createStub(MailTransportInterface::class);
        $transport->method('send')->willThrowException(new RuntimeException('Connection refused'));
        $mail = new CountedMail($transport);

        try {
            $mail->send(self::message());
            static::fail('The failure was not passed on');
        } catch (RuntimeException $failure) {
            static::assertSame(['Connection refused', 0, 1], [$failure->getMessage(), $mail->sent, $mail->failed]);
        }
    }

    #[Test]
    public function countsWhatItSends(): void
    {
        $mail = new CountedMail(static::createStub(MailTransportInterface::class));

        $mail->send(self::message());
        $mail->send(self::message());

        static::assertSame([2, 0], [$mail->sent, $mail->failed]);
    }

    #[Test]
    public function failsEachWithoutTheHostsMail(): void
    {
        $mail = new CountedMail(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('this site lends add-ons no mail');

        $mail->send(self::message());
    }

    #[Test]
    #[DataProvider('summaryProvider')]
    public function saysWhatWentOut(int $sent, int $failed, string $expected): void
    {
        $mail         = new CountedMail(null);
        $mail->sent   = $sent;
        $mail->failed = $failed;

        static::assertSame($expected, $mail->summary());
    }
}
