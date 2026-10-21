<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Schema;

use Override;
use PhpDb\Migration\AbstractMigration;
use PhpDb\Sql\Ddl\Column\Integer;
use PhpDb\Sql\Ddl\Column\Text;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\CreateTable;

/**
 * The table holding the key the form builder signs its links to uploads
 * with, one row made the first time a link is signed.
 *
 * @internal Run by the host's migration runner.
 *
 * @mago-expect analysis:missing-constructor AbstractMigration sets its adapter and inspector in up() and preview().
 */
final class Version20261021160000CreateKeyTable extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create ext_formbuilder_key, for the key that signs links to uploads';
    }

    #[Override]
    public function getVersion(): string
    {
        return '20261021160000';
    }

    #[Override]
    protected function define(): void
    {
        $this->ensureTable('ext_formbuilder_key', static function (CreateTable $table): void {
            $table->addColumn(new Integer('id'));
            $table->addColumn(new Text('secret'));
            $table->addConstraint(new PrimaryKey('id'));
        });
    }
}
