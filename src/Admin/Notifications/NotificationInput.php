<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\Extension\Formbuilder\Admin\Fields\Posted;
use Contenir\FormBuilder\Definition\NotificationDefinition;

/**
 * A notification's details as its Details screen posts them, as the
 * built-in module read them: a name (Notification when left empty), when it
 * sends, who it goes to, is from and replies to, its subject and whether it
 * sends. Its layout is saved on its own screen, and its place in the list
 * isn't posted, so a save keeps both.
 */
final readonly class NotificationInput
{
    /**
     * @mago-expect lint:excessive-parameter-list One property per detail; built by fromBody().
     */
    private function __construct(
        public string $name,
        public string $trigger,
        public string $to,
        public ?string $from,
        public ?string $replyTo,
        public string $subject,
        public bool $enabled,
    ) {}

    /**
     * @param array<array-key, mixed> $body
     */
    public static function fromBody(array $body): self
    {
        $posted = static fn(string $name): mixed => $body[$name] ?? null;

        return new self(
            Posted::optional($posted('name')) ?? 'Notification',
            Posted::optional($posted('trigger')) ?? 'submit',
            Posted::text($posted('to_address')),
            Posted::optional($posted('from_address')),
            Posted::optional($posted('reply_to')),
            Posted::text($posted('subject')),
            Posted::ticked($posted('enabled')),
        );
    }

    /**
     * A notification's details as its screen shows them: a new one's,
     * sending, or a saved one's.
     *
     * @return array{name: string, to_address: string, from_address: string, reply_to: string, subject: string, enabled: bool}
     */
    public static function values(?NotificationDefinition $notification): array
    {
        return [
            'name'         => $notification->name ?? '',
            'to_address'   => $notification->toAddress ?? '',
            'from_address' => $notification->fromAddress ?? '',
            'reply_to'     => $notification->replyTo ?? '',
            'subject'      => $notification->subject ?? '',
            'enabled'      => $notification->enabled ?? true,
        ];
    }

    /**
     * What the form builder stores.
     *
     * @return array{name: string, trigger: string, to_address: string, from_address: ?string, reply_to: ?string, subject: string, enabled: bool}
     */
    public function data(): array
    {
        return [
            'name'         => $this->name,
            'trigger'      => $this->trigger,
            'to_address'   => $this->to,
            'from_address' => $this->from,
            'reply_to'     => $this->replyTo,
            'subject'      => $this->subject,
            'enabled'      => $this->enabled,
        ];
    }

    /**
     * What stops the details saving, by the field at fault.
     *
     * @return array<string, string>
     */
    public function problems(): array
    {
        $problems = [];
        if ('' === $this->to) {
            $problems['to_address'] = 'A recipient address is required.';
        }

        if ('' === $this->subject) {
            $problems['subject'] = 'Subject is required.';
        }

        return $problems;
    }

    /**
     * The details as the screen shows them again, as they were posted.
     *
     * @return array{name: string, to_address: string, from_address: string, reply_to: string, subject: string, enabled: bool}
     */
    public function shown(): array
    {
        return [
            'name'         => $this->name,
            'to_address'   => $this->to,
            'from_address' => $this->from ?? '',
            'reply_to'     => $this->replyTo ?? '',
            'subject'      => $this->subject,
            'enabled'      => $this->enabled,
        ];
    }
}
