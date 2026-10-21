<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Persistence\Forms;
use Contenir\Extension\Formbuilder\Persistence\SiteDatabase;
use Contenir\Extension\Formbuilder\Uploads\UploadsMover;
use Contenir\Extension\Formbuilder\Uploads\UploadStorage;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Contenir\Storage\Exception\StorageException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Move uploads to private storage, on the Forms home, which asks first:
 * moves every form's uploads from the site's public storage to the private
 * storage the host lends, then goes back to the Forms home saying how many
 * it moved and how many it couldn't find (FRM-2).
 */
final class UploadsMoveHandler implements HandlerInterface
{
    public const string NAME = 'uploads-move';

    private static function plural(int $count): string
    {
        return 1 === $count ? '1 upload' : "{$count} uploads";
    }

    /**
     * @throws PersistenceException        When the forms or their entries can't be read.
     * @throws StorageException            When a file can't be read, kept or deleted.
     * @throws RuntimeException            When no temporary file can be made for one.
     * @throws ContainerExceptionInterface When the host can't give the site's database or its storage.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $result  = HandlerResult::redirect(Urls::handler(FormsHandler::NAME));
        $storage = UploadStorage::of($context);
        if ('POST' !== $request->getMethod()) {
            return $result;
        }

        if (null === $storage) {
            return $result->withMessage('danger', 'This site has no private storage, so uploads can\'t be moved.');
        }

        $counts = (new UploadsMover(Forms::of($context), $storage, SiteDatabase::of($context)))->moveAll();
        $moved  = 0 === $counts['moved']
            ? 'No uploads were left in public storage.'
            : 'Moved ' . self::plural($counts['moved']) . ' to private storage.';
        $missing = 0 === $counts['missing'] ? '' : ' ' . self::plural($counts['missing']) . ' could not be found.';

        return $result->withMessage(0 === $counts['missing'] ? 'success' : 'warning', $moved . $missing);
    }
}
