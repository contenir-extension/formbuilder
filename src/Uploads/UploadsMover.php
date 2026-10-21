<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Uploads;

use Contenir\Extension\Formbuilder\Persistence\Forms;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Contenir\Storage\Exception\StorageException;
use PhpDb\Adapter\AdapterInterface;
use RuntimeException;

use function array_map;
use function count;
use function is_numeric;

/**
 * Moves every form's uploads from public storage to private, entry by entry
 * a batch at a time, and points an entry at a file's new path when private
 * storage named it otherwise (FRM-2). Running it again moves nothing more.
 */
final readonly class UploadsMover
{
    private const int BATCH = 200;

    public function __construct(
        private Forms $forms,
        private UploadStorage $storage,
        private AdapterInterface $database,
    ) {}

    /**
     * @param array<string, mixed> $entry
     */
    private static function id(array $entry): int
    {
        return is_numeric($entry['form_entry_id'] ?? null) ? (int) $entry['form_entry_id'] : 0;
    }

    /**
     * How many files were moved and how many neither storage has.
     *
     * @return array{moved: int, missing: int}
     *
     * @throws PersistenceException When the forms or their entries can't be read.
     * @throws StorageException     When a file can't be read, kept or deleted.
     * @throws RuntimeException     When no temporary file can be made for one.
     */
    public function moveAll(): array
    {
        $counts = ['moved' => 0, 'missing' => 0];
        foreach ($this->forms->loader->loadAll() as $form) {
            $beforeId = null;
            do {
                $entries = $this->forms->entries->listEntriesBefore((int) $form->id, $beforeId, self::BATCH);
                $ids     = array_map(self::id(...), $entries);
                $values  = $this->forms->entries->fetchValuesForEntries($ids);
                foreach ($ids as $id) {
                    $counts   = $this->moveEntry($form, $id, $values[$id] ?? [], $counts);
                    $beforeId = $id;
                }
            } while (self::BATCH === count($entries));
        }

        return $counts;
    }

    /**
     * @param list<array<string, mixed>>         $values
     * @param array{moved: int, missing: int} $counts
     *
     * @return array{moved: int, missing: int}
     *
     * @throws StorageException When a file can't be read, kept or deleted.
     * @throws RuntimeException When no temporary file can be made for one.
     */
    private function moveEntry(FormDefinition $form, int $id, array $values, array $counts): array
    {
        foreach (EntryUploads::of($form, $values) as $field => $path) {
            $moved = $this->storage->moveToPrivate($path);
            if (null === $moved) {
                ++$counts['missing'];
                continue;
            }

            if ($moved->fromPublic) {
                ++$counts['moved'];
            }

            if ($moved->path !== $path) {
                $this->database->query(
                    'UPDATE form_entry_value SET value_text = ? WHERE form_entry_id = ? AND field_name = ?',
                    [$moved->path, $id, $field],
                );
            }
        }

        return $counts;
    }
}
