<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Template;

use Contenir\Extension\Formbuilder\Template\FormTemplate;
use Contenir\Extension\Formbuilder\Template\FormTemplateRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;
use function dirname;
use function get_object_vars;

#[Group('unit')]
final class FormTemplateRegistryTest extends TestCase
{
    #[Test]
    public function knowsNoTemplateBySlugItDoesNotHave(): void
    {
        $registry = new FormTemplateRegistry();

        static::assertSame([false, null, true], [
            $registry->has('does-not-exist'),
            $registry->get('does-not-exist'),
            $registry->has('contact'),
        ]);
    }

    #[Test]
    public function letsAnExtraTemplateReplaceABuiltInOneBySlug(): void
    {
        $custom = new FormTemplate(
            slug: 'contact',
            name: 'Custom contact',
            description: 'Override',
            icon: 'mail',
            titleSuggestion: 'Custom Contact',
            slugSuggestion: 'custom-contact',
            sections: [],
        );

        static::assertSame('Custom contact', (new FormTemplateRegistry([$custom]))->get('contact')?->name);
    }

    #[Test]
    public function listsTheBuiltInTemplatesInTheMenusOrder(): void
    {
        static::assertSame(
            ['contact', 'newsletter', 'product-enquiry', 'event-registration', 'booking-request', 'feedback'],
            array_map(
                static fn(FormTemplate $template): string => $template->slug,
                (new FormTemplateRegistry())->all(),
            ),
        );
    }

    #[Test]
    public function seedsEachTemplateAsTheSnapshotRecords(): void
    {
        $seeds = [];
        foreach ((new FormTemplateRegistry())->all() as $template) {
            $seeds[$template->slug] = get_object_vars($template);
        }

        static::assertSame(require dirname(__DIR__, levels: 2) . '/TestAsset/form-templates.php', $seeds);
    }

    #[Test]
    public function seedsTheContactTemplatesTitleSlugAndSection(): void
    {
        $contact = (new FormTemplateRegistry())->get('contact');

        static::assertSame(
            ['Contact us', 'contact', 1, 'main'],
            [
                $contact?->titleSuggestion,
                $contact?->slugSuggestion,
                count($contact->sections ?? []),
                $contact?->sections[0]['key'] ?? null,
            ],
        );
    }
}
