<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin;

use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Formbuilder\FormbuilderExtension;
use Contenir\Extension\Formbuilder\Persistence\SiteDatabase;
use Contenir\Extension\Formbuilder\Tests\Trait\FormsDatabaseTrait;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function array_key_last;
use function array_map;
use function iterator_to_array;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function trim;

/**
 * The Forms area's home on the API's in-memory host, over a migrated site
 * database: a card per form, behind the Forms area's permission.
 */
#[Group('integration')]
final class FormsHandlerTest extends TestCase
{
    use FormsDatabaseTrait;

    private InMemoryHost $host;

    /**
     * @return array<string, array{list<string>, int}>
     */
    public static function guardProvider(): array
    {
        return [
            'the Forms area'                 => [['forms'], 200],
            'the Forms area, to view'        => [['forms:view'], 200],
            'the Forms area, to update only' => [['forms:update'], 403],
            'the handler by its own name'    => [['extension.formbuilder.forms'], 403],
            'nothing'                        => [[], 403],
        ];
    }

    #[Test]
    public function drawsACardPerFormByTitleWithItsStatusSlugAndLayout(): void
    {
        $this->addForm('Newsletter', 'newsletter', 'inactive', 'stepped');
        $this->addForm('Contact <us>', 'contact');
        $xpath = $this->home();

        static::assertSame(
            [
                ['Contact <us>', 'Newsletter'],
                ['Active',       'Inactive'],
                ['contact',      'newsletter'],
                ['Single page',  'Stepped'],
            ],
            [
                $this->texts($xpath, '//h2[@class="card__title"]/a'),
                $this->texts($xpath, '//span[contains(@class, "badge")]'),
                $this->texts($xpath, '//code'),
                $this->texts($xpath, '//dt[.="Layout"]/following-sibling::dd'),
            ],
        );
    }

    /**
     * @param list<string> $grants
     */
    #[Test]
    #[DataProvider('guardProvider')]
    public function isGuardedByTheFormsArea(array $grants, int $status): void
    {
        static::assertSame($status, $this->get($grants)->getStatusCode());
    }

    #[Test]
    public function linksEachCardToTheFormsScreens(): void
    {
        $id    = $this->addForm('Contact', 'contact');
        $xpath = $this->home();

        static::assertSame(
            [
                "/admin/extension/formbuilder/entries?form={$id}",
                "/admin/extension/formbuilder/entries?form={$id}",
                "/admin/extension/formbuilder/fields?form={$id}",
                "/admin/extension/formbuilder/preview?form={$id}",
                "/admin/extension/formbuilder/notifications?form={$id}",
                "/admin/extension/formbuilder/webhooks?form={$id}",
                "/admin/extension/formbuilder/form?form={$id}",
            ],
            $this->texts($xpath, '//section[@class="card"]//a/@href'),
        );
    }

    #[Test]
    public function offersEachTemplateToStartAFormFrom(): void
    {
        $hrefs = $this->texts($this->home(), '//div[@id="forms-templates"]/a/@href');

        static::assertSame(
            ['/admin/extension/formbuilder/form-add?template=contact', '/admin/extension/formbuilder/form-add'],
            [$hrefs[0] ?? null, $hrefs[array_key_last($hrefs)] ?? null],
        );
    }

    #[Test]
    public function saysThereAreNoFormsYet(): void
    {
        static::assertSame(['No forms yet'], $this->texts($this->home(), '//h2[@class="empty__title"]'));
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost($http, $http, new FrozenClock(new DateTimeImmutable('2026-10-20 09:00')));
        $this->host->boot(
            [FormbuilderExtension::HANDLE => new FormbuilderExtension()],
            [FormbuilderExtension::HANDLE],
            [SiteDatabase::SERVICE => $this->database],
        );
    }

    /**
     * @param list<string> $grants
     */
    private function get(array $grants): ResponseInterface
    {
        return $this->host->handle(
            (new Psr17Factory())->createServerRequest('GET', '/admin/extension/formbuilder/forms'),
            $grants,
        );
    }

    private function home(): DOMXPath
    {
        $response = $this->get([FormbuilderExtension::AREA]);
        static::assertSame(200, $response->getStatusCode());

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML("<?xml encoding=\"UTF-8\">{$response->getBody()}");
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return new DOMXPath($document);
    }

    /**
     * @return list<string>
     */
    private function texts(DOMXPath $xpath, string $query): array
    {
        return array_map(
            static fn($node): string => trim($node->textContent),
            iterator_to_array($xpath->query($query)),
        );
    }
}
