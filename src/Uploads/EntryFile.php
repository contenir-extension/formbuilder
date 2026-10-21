<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Uploads;

use Closure;
use Contenir\Extension\Formbuilder\Persistence\Forms;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Persistence\PersistenceException;

use function basename;
use function fclose;
use function stream_copy_to_stream;

/**
 * The file one of an entry's upload fields holds, found in storage and open
 * for sending: its name, and what copies it to the stream it is sent on.
 */
final readonly class EntryFile
{
    /**
     * @param Closure(resource): void $writer
     */
    private function __construct(
        public string $name,
        public Closure $writer,
    ) {}

    /**
     * The file, or null when the field holds none or storage hasn't it.
     *
     * @throws PersistenceException When the entry's values can't be read.
     */
    public static function of(
        Forms $forms,
        ?UploadStorage $storage,
        FormDefinition $form,
        int $entryId,
        string $field,
    ): ?self {
        $path   = EntryUploads::of($form, $forms->entries->fetchValues($entryId))[$field] ?? null;
        $source = null === $path || null === $storage ? null : $storage->open($path);
        if (null === $path || null === $source) {
            return null;
        }

        return new self(basename($path), static function ($target) use ($source): void {
            stream_copy_to_stream($source, $target);
            fclose($source);
        });
    }
}
