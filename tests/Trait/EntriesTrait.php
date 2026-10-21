<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Trait;

use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Contenir\FormBuilder\Repository\PhpDbEntryRepository;
use DateTimeImmutable;

/**
 * Entries of a form laid out for the fields editor, recorded as a visitor's
 * submission would be, at the time given. Call setUpHost() from setUp().
 */
trait EntriesTrait
{
    use FieldsEditorTrait;

    /**
     * @param array<string, mixed> $values
     *
     * @throws PersistenceException
     */
    private function addEntry(
        int $form,
        array $values = [],
        string $status = 'complete',
        string $at = '2026-10-19 10:00',
        string $ip = '192.0.2.10',
    ): int {
        return (new PhpDbEntryRepository($this->database, new FrozenClock(new DateTimeImmutable($at))))->record(
            $form,
            $values,
            $status,
            $ip,
            null,
        );
    }

    /**
     * The entry as stored now.
     *
     * @return array<string, mixed>|null
     *
     * @throws PersistenceException
     */
    private function storedEntry(int $id): ?array
    {
        return (new PhpDbEntryRepository($this->database))->findEntry($id);
    }
}
