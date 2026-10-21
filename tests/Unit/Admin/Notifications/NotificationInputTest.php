<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Notifications;

use Contenir\Extension\Formbuilder\Admin\Notifications\NotificationInput;
use Contenir\FormBuilder\Definition\NotificationDefinition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class NotificationInputTest extends TestCase
{
    #[Test]
    public function keepsAPostedTriggerAndShowsASavedNotification(): void
    {
        static::assertSame(
            [
                'later',
                [
                    'name'         => 'Team',
                    'to_address'   => '',
                    'from_address' => '',
                    'reply_to'     => 'b@example.com',
                    'subject'      => '',
                    'enabled'      => false,
                ],
                [
                    'name'         => 'Team',
                    'to_address'   => 'a@example.com',
                    'from_address' => '',
                    'reply_to'     => 'b@example.com',
                    'subject'      => 'Hi',
                    'enabled'      => false,
                ],
                [
                    'name'         => '',
                    'to_address'   => '',
                    'from_address' => '',
                    'reply_to'     => '',
                    'subject'      => '',
                    'enabled'      => true,
                ],
            ],
            [
                NotificationInput::fromBody(['trigger' => 'later'])->data()['trigger'],
                NotificationInput::fromBody(['name' => 'Team', 'reply_to' => 'b@example.com'])->shown(),
                NotificationInput::values(new NotificationDefinition(
                    id: 1,
                    name: 'Team',
                    toAddress: 'a@example.com',
                    replyTo: 'b@example.com',
                    subject: 'Hi',
                    enabled: false,
                )),
                NotificationInput::values(null),
            ],
        );
    }

    #[Test]
    public function readsTheDetailsAsTheBuiltInModuleDid(): void
    {
        $input = NotificationInput::fromBody([
            'name'         => '  ',
            'trigger'      => '',
            'to_address'   => ' a@example.com ',
            'from_address' => ' site@example.com ',
            'reply_to'     => '',
            'subject'      => ' Hi ',
            'enabled'      => '0',
        ]);

        static::assertSame(
            [
                [
                    'name'         => 'Notification',
                    'trigger'      => 'submit',
                    'to_address'   => 'a@example.com',
                    'from_address' => 'site@example.com',
                    'reply_to'     => null,
                    'subject'      => 'Hi',
                    'enabled'      => false,
                ],
                [
                    'name'         => 'Notification',
                    'to_address'   => 'a@example.com',
                    'from_address' => 'site@example.com',
                    'reply_to'     => '',
                    'subject'      => 'Hi',
                    'enabled'      => false,
                ],
                [],
            ],
            [$input->data(), $input->shown(), $input->problems()],
        );
    }
}
