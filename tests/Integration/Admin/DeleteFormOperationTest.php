<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin;

use Contenir\Extension\Formbuilder\Admin\DeleteFormOperation;
use Contenir\Extension\Formbuilder\Persistence\SiteDatabase;
use Contenir\Extension\Formbuilder\Tests\TestAsset\Operation\PressedOperation;
use Contenir\Extension\Formbuilder\Tests\Trait\FormsDatabaseTrait;
use Contenir\FormBuilder\Loader\PhpDbFormLoader;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Delete form, pressed on a form's Settings tab.
 */
#[Group('integration')]
final class DeleteFormOperationTest extends TestCase
{
    use FormsDatabaseTrait;

    #[Test]
    public function deletesNothingWhenNoFormIsNamed(): void
    {
        $id     = $this->addForm('Contact', 'contact');
        $result = (new DeleteFormOperation())->handle(
            new PressedOperation([], [
                SiteDatabase::SERVICE => $this->database,
            ]),
        );

        static::assertSame(
            [null, [['level' => 'warning', 'text' => 'That form no longer exists.']], 'contact'],
            [$result->redirect(), $result->messages(), (new PhpDbFormLoader($this->database))->loadById($id)?->slug],
        );
    }

    #[Test]
    public function deletesTheFormAndGoesBackToTheFormsHome(): void
    {
        $id     = $this->addForm('Contact', 'contact');
        $result = (new DeleteFormOperation())->handle(
            new PressedOperation(['form' => (string) $id], [
                SiteDatabase::SERVICE => $this->database,
            ]),
        );

        static::assertSame(
            [['handle' => 'formbuilder', 'name' => 'forms'], [['level' => 'success', 'text' => 'Form deleted.']], null],
            [$result->redirect(), $result->messages(), (new PhpDbFormLoader($this->database))->loadById($id)],
        );
    }

    #[Test]
    public function refusesToDeleteWithoutTheSitesDatabase(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The host offers no site database as "extensions.database.site"');

        (new DeleteFormOperation())->handle(
            new PressedOperation(['form' => '3'], [
                SiteDatabase::SERVICE => new stdClass(),
            ]),
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }
}
