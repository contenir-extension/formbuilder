<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Api\Handler\Tab;
use Contenir\Extension\Formbuilder\Admin\FormHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\FormbuilderHostTrait;
use Contenir\FormBuilder\Loader\PhpDbFormLoader;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_values;

/**
 * A form's Settings tab over a migrated site database, as the CMS's
 * built-in Forms module had it.
 */
#[Group('integration')]
final class FormHandlerTest extends TestCase
{
    use FormbuilderHostTrait;

    /**
     * @return array<string, array{string, string, list<string>, int}>
     */
    public static function guardProvider(): array
    {
        return [
            'settings, to view'           => ['GET', 'form', ['forms:view'], 200],
            'settings, posted empty'      => ['POST', 'form', ['forms:update'], 200],
            'settings, saved by a viewer' => ['POST', 'form', ['forms:view'], 403],
            'adding, by a role adding'    => ['GET', 'form-add', ['forms:add'], 200],
            'adding, by a role updating'  => ['GET', 'form-add', ['forms:update', 'forms:view'], 403],
        ];
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function missingProvider(): array
    {
        return ['no form named' => [[]], 'a form that is gone' => [['form' => '99']]];
    }

    /**
     * @param list<string> $grants
     */
    #[Test]
    #[DataProvider('guardProvider')]
    public function guardsTheScreensByTheFormsArea(string $method, string $handler, array $grants, int $status): void
    {
        $id       = $this->addForm('Contact', 'contact');
        $response = $this->host->handle(
            (new Psr17Factory())->createServerRequest($method, "/admin/extension/formbuilder/{$handler}?form={$id}"),
            $grants,
        );

        static::assertSame($status, $response->getStatusCode());
    }

    #[Test]
    public function savesAFormsSettingsAndComesBackToThem(): void
    {
        $id     = $this->addForm('Contact', 'contact');
        $result = $this->settings($id, ['title' => 'Get in touch', 'slug' => 'touch', 'retention_days' => '30']);
        $form   = (new PhpDbFormLoader($this->database))->loadById($id);

        static::assertSame(
            [
                ResultKind::Redirect,
                "/admin/extension/formbuilder/form?form={$id}",
                [['level' => 'success', 'text' => 'Form updated.']],
                'Get in touch',
                'touch',
                30,
            ],
            [$result->kind, $result->url, $result->messages, $form?->title, $form?->slug, $form?->retentionDays],
        );
    }

    #[Test]
    public function saysWhatStopsTheSettingsSavingAndKeepsWhatWasTyped(): void
    {
        $this->addForm('Newsletter', 'newsletter');
        $id     = $this->addForm('Contact', 'contact');
        $result = $this->settings($id, ['title' => 'Contact', 'slug' => 'newsletter', 'retention_days' => '0']);

        static::assertSame(
            [
                ResultKind::Screen,
                [
                    ['level' => 'danger', 'text' => 'Slug is already in use.'],
                    ['level' => 'danger', 'text' => 'Retention must be a positive number of days, or empty.'],
                ],
                'newsletter',
                'contact',
            ],
            [
                $result->kind,
                $result->messages,
                $result->screen?->form?->field('slug')?->default,
                (new PhpDbFormLoader($this->database))->loadById($id)?->slug,
            ],
        );
    }

    /**
     * @param array<string, string> $query
     */
    #[Test]
    #[DataProvider('missingProvider')]
    public function sendsAFormThatIsGoneBackToTheFormsHome(array $query): void
    {
        $result = (new FormHandler())->handle(
            (new ServerRequest('GET', '/admin/extension/formbuilder/form'))->withQueryParams($query),
            $this->context(),
        );

        static::assertSame(
            ['/admin/extension/formbuilder/forms', 'That form no longer exists.'],
            [$result->url, $result->messages[0]['text'] ?? null],
        );
    }

    #[Test]
    public function showsAFormsSettingsWithItsTabsAndDelete(): void
    {
        $id     = $this->addForm('Contact', 'contact');
        $screen = $this->settings($id)->screen;
        $tabs   = $screen->tabs ?? [];

        static::assertSame(
            ['Contact', 'Contact', 'contact', 'delete', 'delete', true, true, 'form', ['form' => $id]],
            [
                $screen?->title,
                $screen?->form?->field('title')?->default,
                $screen?->form?->field('slug')?->default,
                $screen?->operations[0]->name ?? null,
                $screen?->operations[0]->privilege ?? null,
                $screen?->operations[0]->confirm ?? null,
                $screen?->operations[0]->destructive ?? null,
                array_values(array_filter($tabs, static fn(Tab $tab): bool => $tab->current))[0]->handler ?? null,
                $tabs[0]->query ?? null,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * @param array<string, string>|null $posted Posted as the screen's form, or null to look
     */
    private function settings(int $id, ?array $posted = null): HandlerResult
    {
        $request = (new ServerRequest(
            null === $posted ? 'GET' : 'POST',
            '/admin/extension/formbuilder/form',
        ))->withQueryParams(['form' => (string) $id])
            ->withParsedBody($posted);

        return (new FormHandler())->handle($request, $this->context());
    }
}
