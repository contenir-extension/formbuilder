<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Formbuilder\Admin\AddFormHandler;
use Contenir\Extension\Formbuilder\Tests\TestAsset\FormShape;
use Contenir\Extension\Formbuilder\Tests\Trait\FormbuilderHostTrait;
use Contenir\FormBuilder\Loader\PhpDbFormLoader;
use Nyholm\Psr7\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Add form over a migrated site database: blank, or from a template, then
 * on to the new form's fields.
 */
#[Group('integration')]
final class AddFormHandlerTest extends TestCase
{
    use FormbuilderHostTrait;

    #[Test]
    public function addsABlankFormWithOneEmptyRowAndOpensItsFields(): void
    {
        $result = $this->add([], ['title' => 'Survey']);
        $form   = (new PhpDbFormLoader($this->database))->loadBySlug('survey');

        static::assertSame(
            ["/admin/extension/formbuilder/fields?form={$form?->id}", 'Form created.', [1, 1, 1, 0]],
            [$result->url, $result->messages[0]['text'] ?? null, FormShape::counts($form)],
        );
    }

    #[Test]
    public function addsAFormFromATemplateWithItsFields(): void
    {
        $result = $this->add(['template' => 'contact'], ['title' => 'Contact us', 'slug' => 'contact']);

        static::assertSame(
            ['Form created from "Contact form" template.', ['name', 'email', 'message']],
            [
                $result->messages[0]['text'] ?? null,
                FormShape::names((new PhpDbFormLoader($this->database))->loadBySlug('contact')),
            ],
        );
    }

    #[Test]
    public function ignoresATemplateItDoesNotHave(): void
    {
        static::assertSame('', $this->add(['template' => 'quiz'])->screen?->form?->field('title')?->default);
    }

    #[Test]
    public function offersABlankFormWithoutTabsOrOperations(): void
    {
        $screen = $this->add()->screen;

        static::assertSame(
            ['Add form', 'Name the form and choose how it behaves. You add its fields next.', [], 'Submit', []],
            [
                $screen?->title,
                $screen?->intro,
                $screen->tabs ?? null,
                $screen?->form?->field('submit_label')?->default,
                $screen->operations ?? null,
            ],
        );
    }

    #[Test]
    public function offersAFormStartedFromATemplate(): void
    {
        $screen = $this->add(['template' => 'contact'])->screen;

        static::assertSame(
            ['Starting from the Contact form template: its fields are added for you.', 'Contact us', 'contact'],
            [$screen?->intro, $screen?->form?->field('title')?->default, $screen?->form?->field('slug')?->default],
        );
    }

    #[Test]
    public function refusesANewFormWithoutATitle(): void
    {
        $result = $this->add([], ['title' => '']);

        static::assertSame(
            [ResultKind::Screen, 'Title is required.', []],
            [$result->kind, $result->messages[0]['text'] ?? null, (new PhpDbFormLoader($this->database))->loadAll()],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * @param array<string, string>      $query
     * @param array<string, string>|null $posted
     */
    private function add(array $query = [], ?array $posted = null): HandlerResult
    {
        $request = (new ServerRequest(
            null === $posted ? 'GET' : 'POST',
            '/admin/extension/formbuilder/form-add',
        ))->withQueryParams($query)
            ->withParsedBody($posted);

        return (new AddFormHandler())->handle($request, $this->context());
    }
}
