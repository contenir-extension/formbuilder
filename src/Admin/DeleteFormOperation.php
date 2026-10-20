<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Operation\OperationContextInterface;
use Contenir\Extension\Api\Operation\OperationHandlerInterface;
use Contenir\Extension\Api\Operation\OperationResult;
use Contenir\Extension\Api\Operation\OperationResultInterface;
use Contenir\Extension\Formbuilder\FormbuilderExtension;
use Contenir\Extension\Formbuilder\Persistence\SiteDatabase;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Contenir\FormBuilder\Repository\PhpDbFormRepository;
use LogicException;
use Override;
use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerExceptionInterface;

use function is_numeric;

/**
 * Delete form, on a form's Settings tab: deletes the form with its fields,
 * notifications, integrations and entries, then goes back to the Forms
 * area.
 */
final class DeleteFormOperation implements OperationHandlerInterface
{
    /**
     * @throws PersistenceException        When the form can't be deleted.
     * @throws ContainerExceptionInterface When the host can't give the site's database.
     * @throws LogicException              When the host offers no site database.
     *
     * @mago-expect analysis:mixed-assignment The posted id is untyped; it is checked before use.
     */
    #[Override]
    public function handle(OperationContextInterface $context): OperationResultInterface
    {
        $id = $context->param('form');
        if (! is_numeric($id)) {
            return OperationResult::message('warning', 'That form no longer exists.');
        }

        $database = $context->services()->get(SiteDatabase::SERVICE);
        if (! $database instanceof AdapterInterface) {
            throw new LogicException('The host offers no site database as "' . SiteDatabase::SERVICE . '"');
        }

        (new PhpDbFormRepository($database))->deleteForm((int) $id);

        return OperationResult::redirectTo([
            'handle' => FormbuilderExtension::HANDLE,
            'name'   => FormsHandler::NAME,
        ])->withMessage('success', 'Form deleted.');
    }
}
