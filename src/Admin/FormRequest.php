<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Formbuilder\Admin\Fields\Posted;
use Contenir\Extension\Formbuilder\Persistence\Forms;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Persistence\PersistenceException;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_string;

/**
 * One request to a form's screens: the form it names, its id, the
 * persistence to change it with, and what the request asked.
 */
final readonly class FormRequest
{
    private function __construct(
        public ServerRequestInterface $request,
        public ExtensionContextInterface $context,
        public Forms $forms,
        public FormDefinition $form,
        public int $id,
    ) {}

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

        return new self($request, $context, $forms, $form, $id);
    }

    /**
     * Everything posted, in the request's body.
     *
     * @return array<array-key, mixed>
     */
    public function body(): array
    {
        $body = $this->request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    /**
     * The whole number the request names as $name, in its address or its
     * body; zero when it names none.
     */
    public function number(string $name): int
    {
        return Posted::number($this->request->getQueryParams()[$name] ?? $this->body()[$name] ?? null);
    }

    /**
     * Whether the request posts.
     */
    public function posts(): bool
    {
        return 'POST' === $this->request->getMethod();
    }

    /**
     * What was posted as $name, as typed; empty when it isn't text.
     */
    public function text(string $name): string
    {
        $body = $this->body();

        return is_string($body[$name] ?? null) ? $body[$name] : '';
    }
}
