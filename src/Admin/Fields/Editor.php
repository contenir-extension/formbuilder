<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\FormId;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Contenir\Extension\Formbuilder\Persistence\Forms;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Contenir\FormBuilder\Repository\PhpDbFormRepository;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;

/**
 * One request to the fields editor: the form it is about, laid out, and
 * what it asked. A script's request (the editor's own, sent with
 * X-Requested-With) is answered with a fragment or JSON for the script to
 * use; a plain one, from a page without scripts, goes back to the fields
 * screen.
 */
final readonly class Editor
{
    /**
     * @param array<array-key, mixed> $body
     */
    private function __construct(
        public ServerRequestInterface $request,
        public FormLayout $layout,
        public PhpDbFormRepository $repository,
        private Forms $forms,
        private array $body,
    ) {}

    /**
     * What a request about a form that is gone is answered with: a script
     * learns it wasn't found, a page goes back to the Forms home.
     */
    public static function missing(ServerRequestInterface $request): HandlerResult
    {
        return (
            self::scriptedRequest($request)
                ? HandlerResult::json(['ok' => false, 'error' => 'not_found'], 404)
                : FormId::missing()
        );
    }

    /**
     * The form the request names, or null when it names none or one that is
     * gone.
     *
     * @throws PersistenceException        When the form can't be read.
     * @throws ContainerExceptionInterface When the host can't give the site's database.
     * @throws LogicException              When the host offers no site database.
     */
    public static function of(ServerRequestInterface $request, ExtensionContextInterface $context): ?self
    {
        $forms = Forms::of($context);
        $form  = $forms->loader->loadById(FormId::of($request));
        $id    = $form?->id;
        if (null === $form || null === $id) {
            return null;
        }

        $body = $request->getParsedBody();

        return new self(
            $request,
            FormLayout::of($form, $id),
            $forms->repository,
            $forms,
            is_array($body) ? $body : [],
        );
    }

    private static function scriptedRequest(ServerRequestInterface $request): bool
    {
        return 'XMLHttpRequest' === $request->getHeaderLine('X-Requested-With');
    }

    /**
     * Back to the form's fields screen, saying $message when there is one.
     */
    public function back(?string $message = null): HandlerResult
    {
        $result = HandlerResult::redirect(Urls::handler(FormTabs::FIELDS, ['form' => $this->layout->id]));

        return null === $message ? $result : $result->withMessage('success', $message);
    }

    /**
     * Everything posted, in the request's body.
     *
     * @return array<array-key, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    /**
     * What a request naming no part of the form, or asking what its handler
     * doesn't do, is answered with: a script learns it wasn't found, a page
     * goes back to the fields screen.
     */
    public function nothing(): HandlerResult
    {
        return $this->scripted() ? HandlerResult::json(['ok' => false, 'error' => 'not_found'], 404) : $this->back();
    }

    /**
     * The whole number the request names as $name, in its address or its
     * body; zero when it names none.
     */
    public function number(string $name): int
    {
        return Posted::number($this->request->getQueryParams()[$name] ?? $this->body[$name] ?? null);
    }

    /**
     * Whether the request posts.
     */
    public function posts(): bool
    {
        return 'POST' === $this->request->getMethod();
    }

    /**
     * The form laid out again, after a change.
     *
     * @throws PersistenceException When the form can't be read.
     */
    public function reloaded(): FormLayout
    {
        $form = $this->forms->loader->loadById($this->layout->id) ?? $this->layout->form;

        return FormLayout::of($form, $this->layout->id);
    }

    /**
     * Whether the editor's script sent the request.
     */
    public function scripted(): bool
    {
        return self::scriptedRequest($this->request);
    }
}
