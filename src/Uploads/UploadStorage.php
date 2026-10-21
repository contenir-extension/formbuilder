<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Uploads;

use Closure;
use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Storage\StorageProfile;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Redaction\EntryFilesInterface;
use Contenir\Storage\Exception\InvalidPathException;
use Contenir\Storage\Exception\NotFoundException;
use Contenir\Storage\Exception\StorageException;
use Contenir\Storage\StorageInterface;
use Contenir\Storage\StorageManager;
use Contenir\Storage\UploadInput;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

use function basename;
use function dirname;
use function fclose;
use function stream_copy_to_stream;
use function stream_get_meta_data;
use function tmpfile;

/**
 * Where the form builder's uploads are kept: the private profile the host
 * lends, which no URL reaches, and the site's public storage, where uploads
 * went before they were moved. A file is read from private storage first,
 * then public; deleting an entry's files removes them from both.
 */
final readonly class UploadStorage implements EntryFilesInterface
{
    /**
     * The profile of the host's storage that no URL reaches.
     */
    public const string PRIVATE = StorageProfile::PRIVATE;

    /**
     * @param (Closure(): (resource|false))|null $scratch Opens a temporary file to copy a file through
     *                                                  on its way to private storage; tmpfile() by default
     */
    public function __construct(
        private StorageInterface $private,
        private StorageInterface $public,
        private LoggerInterface $log,
        private ?Closure $scratch = null,
    ) {}

    /**
     * The host's storage, or null when it lends none with a private
     * profile.
     *
     * @throws ContainerExceptionInterface When the host can't give its storage.
     */
    public static function of(ExtensionContextInterface $context): ?self
    {
        $services = $context->services();
        $storage  = $services->has(StorageManager::class) ? $services->get(StorageManager::class) : null;
        if (! $storage instanceof StorageManager || ! $storage->has(self::PRIVATE)) {
            return null;
        }

        return new self($storage->get(self::PRIVATE), $storage->primary(), $context->logger());
    }

    /**
     * Deletes the files the entry's upload fields hold, from both, logging
     * one that can't be deleted.
     */
    #[Override]
    public function delete(FormDefinition $form, int $entryId, array $values): bool
    {
        foreach (EntryUploads::of($form, $values) as $path) {
            foreach ([$this->private, $this->public] as $storage) {
                try {
                    $storage->delete($path);
                } catch (NotFoundException) {
                    continue;
                } catch (Throwable $failure) {
                    $this->log->warning(
                        "The file {$path} of entry {$entryId} of form \"{$form->slug}\" could not be deleted: "
                            . $failure->getMessage(),
                    );

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Moves a file from public storage to private, saying where it is kept
     * now, which may differ from where it was when private storage names it
     * otherwise, or null when neither has it. A file already in private
     * storage stays where it is, and a copy left in public storage is
     * deleted.
     *
     * @throws StorageException When the file can't be read, kept or deleted.
     * @throws RuntimeException When no temporary file can be made for it.
     */
    public function moveToPrivate(string $path): ?Moved
    {
        if ($this->private->exists($path)) {
            if ($this->public->exists($path)) {
                $this->public->delete($path);
            }

            return new Moved($path, fromPublic: false);
        }

        if (! $this->public->exists($path)) {
            return null;
        }

        $copy = $this->copy($path);
        $kept = $this->private->store(
            new UploadInput(stream_get_meta_data($copy)['uri'], basename($path)),
            dirname($path),
        );
        fclose($copy);

        $this->public->delete($path);

        return new Moved($kept->path, fromPublic: true);
    }

    /**
     * The file, open from its start, or null when neither has it or its
     * path isn't one storage accepts.
     *
     * @return resource|null
     *
     * @mago-expect analysis:missing-return-type PHP has no resource type; the docblock names it.
     */
    public function open(string $path)
    {
        foreach ([$this->private, $this->public] as $storage) {
            try {
                return $storage->read($path);
            } catch (NotFoundException|InvalidPathException) {
                continue;
            }
        }

        return null;
    }

    /**
     * A temporary copy of a file in public storage, open, for private
     * storage to keep; closing it deletes it, as the request's end does if
     * keeping it fails.
     *
     * @return resource
     *
     * @throws StorageException When the file can't be read.
     * @throws RuntimeException When no temporary file can be made.
     *
     * @mago-expect analysis:missing-return-type PHP has no resource type; the docblock names it.
     */
    private function copy(string $path)
    {
        $copy = null === $this->scratch ? tmpfile() : ($this->scratch)();
        if (false === $copy) {
            throw new RuntimeException("No temporary file could be made to move {$path}");
        }

        $source = $this->public->read($path);
        stream_copy_to_stream($source, $copy);
        fclose($source);

        return $copy;
    }
}
