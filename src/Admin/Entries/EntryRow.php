<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use DateMalformedStringException;
use DateTimeImmutable;

use function is_numeric;
use function is_string;

/**
 * One entry as the Entries tab shows it: its number, its form, its status,
 * when it was submitted and from what address.
 */
final readonly class EntryRow
{
    public function __construct(
        public int $id,
        public int $formId,
        public string $status,
        public DateTimeImmutable $submittedAt,
        public string $ip,
    ) {}

    /**
     * An entry as the repository read it, whichever driver fetched it.
     *
     * @param array<string, mixed> $stored
     *
     * @throws DateMalformedStringException When its submission time can't be read.
     */
    public static function of(array $stored): self
    {
        return new self(
            self::number($stored['form_entry_id'] ?? null),
            self::number($stored['form_id'] ?? null),
            self::text($stored['status'] ?? null),
            new DateTimeImmutable(self::text($stored['submitted_at'] ?? null)),
            self::text($stored['ip'] ?? null),
        );
    }

    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
