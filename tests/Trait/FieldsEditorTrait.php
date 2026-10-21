<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Trait;

use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryTemplates;
use Contenir\Extension\Formbuilder\FormbuilderExtension;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Loader\PhpDbFormLoader;
use Contenir\FormBuilder\Repository\PhpDbFormRepository;
use DateTimeImmutable;
use LogicException;
use Nyholm\Psr7\ServerRequest;

use function dirname;

/**
 * A form laid out for the fields editor (a section holding a group of two
 * rows, the first with a name and an email field, the second with a
 * message) and requests to the editor's handlers about it, as its script
 * or a page without scripts sends them. Call setUpHost() from setUp().
 */
trait FieldsEditorTrait
{
    use FormbuilderHostTrait;

    /**
     * The handler's answer to the editor's script asking about the form.
     *
     * @param class-string<HandlerInterface> $handler
     * @param array<string, scalar>          $query
     * @param array<string, mixed>           $body
     */
    private function answer(string $handler, string $method, array $query, array $body = []): HandlerResult
    {
        return (new $handler())->handle(
            $this->request($method, $query, $body)->withHeader('X-Requested-With', 'XMLHttpRequest'),
            $this->context(),
        );
    }

    /**
     * The template or fragment a result answered with, drawn as a host
     * would, its tabs as links.
     */
    private function drawn(HandlerResult $result): string
    {
        $template = $result->template ?? $result->fragment ?? throw new LogicException('Not a template answer');

        return (new InMemoryTemplates(
            new Handle(FormbuilderExtension::HANDLE),
            dirname(__DIR__, levels: 2) . '/templates',
            $result->template->tabs ?? [],
        ))->render($template->name, $template->values);
    }

    /**
     * The form as stored now.
     */
    private function form(int $id): ?FormDefinition
    {
        return (new PhpDbFormLoader($this->database))->loadById($id);
    }

    /**
     * A form laid out with a section, a group, two rows and three fields,
     * by id.
     *
     * @return array{form: int, section: int, group: int, rows: list<int>, fields: list<int>}
     */
    private function layOut(string $title = 'Contact', string $slug = 'contact'): array
    {
        $repository = $this->repository();
        $form       = $this->addForm($title, $slug);
        $section    = $repository->createSection($form, ['key' => 'main', 'legend' => 'About you']);
        $group      = $repository->createGroup($section, ['legend' => 'Details']);
        $first      = $repository->createRow($group);
        $second     = $repository->createRow($group);

        return [
            'form'    => $form,
            'section' => $section,
            'group'   => $group,
            'rows'    => [$first, $second],
            'fields'  => [
                $repository->createField($first, ['type' => 'text', 'name' => 'name', 'label' => 'Name']),
                $repository->createField($first, [
                    'type'     => 'email',
                    'name'     => 'email',
                    'label'    => 'Email',
                    'required' => true,
                ]),
                $repository->createField($second, ['type' => 'textarea', 'name' => 'message', 'show_label' => false]),
            ],
        ];
    }

    /**
     * The handler's answer to a page without the editor's scripts asking
     * about the form.
     *
     * @param class-string<HandlerInterface> $handler
     * @param array<string, scalar>          $query
     * @param array<string, mixed>           $body
     */
    private function page(string $handler, string $method, array $query, array $body = []): HandlerResult
    {
        return (new $handler())->handle($this->request($method, $query, $body), $this->context());
    }

    private function repository(): PhpDbFormRepository
    {
        return new PhpDbFormRepository($this->database, new FrozenClock(new DateTimeImmutable('2026-10-21 09:00')));
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, mixed>  $body
     */
    private function request(string $method, array $query, array $body): ServerRequest
    {
        return (new ServerRequest($method, '/admin/extension/formbuilder/editor'))->withQueryParams($query)
            ->withParsedBody($body);
    }
}
