<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Module\MenuItem;
use Contenir\Extension\Formbuilder\Admin\FormsToolsMenu;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class FormsToolsMenuTest extends TestCase
{
    /**
     * @return array<string, array{string, list<bool>}>
     */
    public static function pageProvider(): array
    {
        return [
            'the forms list' => ['/admin/extension/formbuilder/forms', [false, false]],
            'redact expired' => ['/admin/extension/formbuilder/entries-redact', [true, false]],
            'move uploads'   => ['/admin/extension/formbuilder/uploads-move', [false, true]],
        ];
    }

    /**
     * @param list<bool> $current
     */
    #[Test]
    #[DataProvider('pageProvider')]
    public function listsTheToolsUnderToolsMarkingTheOneOpen(string $path, array $current): void
    {
        $groups = (new FormsToolsMenu())->groups(
            new ServerRequest('GET', $path),
            $this->createStub(ExtensionContextInterface::class),
        );

        static::assertSame(
            [
                ['Tools'],
                [
                    ['Redact expired entries',          'entries-redact'],
                    ['Move uploads to private storage', 'uploads-move'],
                ],
                $current,
            ],
            [
                array_map(static fn($group): ?string => $group->title, $groups),
                array_map(static fn(MenuItem $item): array => [$item->label, $item->handler], $groups[0]->items),
                array_map(static fn(MenuItem $item): bool => $item->current, $groups[0]->items),
            ],
        );
    }
}
