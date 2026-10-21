<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\FormBuilder\Persistence\PersistenceException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * One of the fields editor's handlers: it finds the form the request names,
 * or answers as Editor::missing() does when it is gone, then edits it.
 *
 * @internal
 */
abstract class AbstractEditorHandler implements HandlerInterface
{
    /**
     * @throws PersistenceException        When the form can't be read or changed.
     * @throws ContainerExceptionInterface When the host can't give the site's database.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    final public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $editor = Editor::of($request, $context);

        return null === $editor ? Editor::missing($request) : $this->edit($editor);
    }

    /**
     * @throws PersistenceException When the form can't be read or changed.
     */
    abstract protected function edit(Editor $editor): HandlerResult;
}
