<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin\Entries;

use Contenir\Extension\Formbuilder\Admin\Entries\EntryRow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class EntryRowTest extends TestCase
{
    #[Test]
    public function readsAnEntryWhicheverDriverFetchedIt(): void
    {
        $entry = EntryRow::of([
            'form_entry_id' => '7',
            'form_id'       => 3,
            'status'        => 'spam',
            'submitted_at'  => '2026-10-19 10:00:00',
            'ip'            => '192.0.2.10',
        ]);

        static::assertSame(
            [7, 3, 'spam', '2026-10-19 10:00', '192.0.2.10'],
            [$entry->id, $entry->formId, $entry->status, $entry->submittedAt->format('Y-m-d H:i'), $entry->ip],
        );
    }

    #[Test]
    public function readsWhatIsMissingAsEmpty(): void
    {
        $entry = EntryRow::of(['submitted_at' => '2026-10-19 10:00:00', 'ip' => null, 'form_id' => 'x']);

        static::assertSame([0, 0, '', ''], [$entry->id, $entry->formId, $entry->status, $entry->ip]);
    }
}
