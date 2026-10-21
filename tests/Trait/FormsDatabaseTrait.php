<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Trait;

use Contenir\Extension\Formbuilder\FormbuilderExtension;
use Contenir\FormBuilder\ConfigProvider;
use PDO;
use PhpDb\Adapter\Adapter;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Migration\MigrationRunner;
use PhpDb\Migration\MismatchStrategy;
use PhpDb\Sqlite;

use function dirname;

/**
 * An in-memory SQLite site database with the form builder's tables,
 * migrated afresh for each test, as the CMS gives the add-on a site's
 * database. Call setUpDatabase() from setUp().
 */
trait FormsDatabaseTrait
{
    private AdapterInterface $database;

    /**
     * Adds a form as the form repository would, returning its id.
     */
    private function addForm(string $title, string $slug, string $status = 'active', string $layout = 'single'): int
    {
        $this->database->query(
            'INSERT INTO form (title, slug, status, layout_mode, submit_label) VALUES (?, ?, ?, ?, ?)',
            [$title, $slug, $status, $layout, 'Submit'],
        );

        return (int) $this->database->getDriver()->getLastGeneratedValue();
    }

    private function setUpDatabase(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $driver         = new Sqlite\Pdo\Driver(connection: new Sqlite\Pdo\Connection($pdo));
        $this->database = new Adapter($driver, new Sqlite\AdapterPlatform($driver));

        foreach ([
            ConfigProvider::SCHEMA_NAMESPACE       => ConfigProvider::schemaPath(),
            FormbuilderExtension::SCHEMA_NAMESPACE => dirname(__DIR__, levels: 2) . '/src/Schema',
        ] as $namespace => $path) {
            $runner = new MigrationRunner(
                $this->database,
                $path,
                $namespace,
                MismatchStrategy::Report,
                new Sqlite\Metadata\Source($this->database),
            );
            $runner->ensureMigrationsTable();
            $runner->runPending();
        }
    }
}
