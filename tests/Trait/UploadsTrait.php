<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Trait;

use Contenir\Extension\Formbuilder\Uploads\UploadStorage;
use Contenir\Storage\Adapter\InMemoryStorage;
use Contenir\Storage\StorageManager;

/**
 * Entries of a form with an upload field, on a host lending storage as the
 * CMS does: the site's public storage, primary, and a private profile, each
 * in memory. Call setUpUploads() from setUp().
 */
trait UploadsTrait
{
    use EntriesTrait;

    private InMemoryStorage $private;

    private InMemoryStorage $public;

    /**
     * A form laid out for the fields editor with an upload field "cv", by id.
     *
     * @return array{form: int, section: int, group: int, rows: list<int>, fields: list<int>}
     */
    private function layOutWithUpload(): array
    {
        $ids = $this->layOut();
        $this->repository()->createField($ids['rows'][1], ['type' => 'file', 'name' => 'cv', 'label' => 'CV']);

        return $ids;
    }

    /**
     * @param array<string, object> $services What else the host offers the add-on, by name
     */
    private function setUpUploads(array $services = []): void
    {
        $this->private = new InMemoryStorage();
        $this->public  = new InMemoryStorage();
        $storage       = new StorageManager();
        $storage->register('local', $this->public, isPrimary: true);
        $storage->register(UploadStorage::PRIVATE, $this->private);
        $this->setUpHost([StorageManager::class => $storage] + $services);
    }
}
