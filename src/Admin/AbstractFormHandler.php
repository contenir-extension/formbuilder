<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\FormBuilder\Persistence\PersistenceException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * One of a form's screens: it finds the form the request names, or sends a
 * form that is gone back to the Forms home, then answers about it.
 *
 * @internal
 */
abstract class AbstractFormHandler implements HandlerInterface
{
    /**
     * @throws PersistenceException        When the form can't be read or changed.
     * @throws ContainerExceptionInterface When the host can't give the site's database.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    final public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $form = FormRequest::of($request, $context);

        return null === $form ? FormId::missing() : $this->answer($form);
    }

    /**
     * @throws PersistenceException When the form can't be read or changed.
     */
    abstract protected function answer(FormRequest $form): HandlerResult;
}
