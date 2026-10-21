<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Admin\Fields;

use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Formbuilder\Admin\Fields\GroupLegendHandler;
use Contenir\Extension\Formbuilder\Admin\Fields\SectionLegendHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Renaming a section or group in place: its legend trimmed, or none when
 * that leaves nothing.
 */
#[Group('integration')]
#[Group('handler')]
final class LegendHandlersTest extends TestCase
{
    use FieldsEditorTrait;

    /**
     * @return array<string, array{class-string, string, string, ?string}>
     */
    public static function legendProvider(): array
    {
        return [
            'a section, renamed' => [SectionLegendHandler::class, 'section', '  Your details ', 'Your details'],
            'a section, cleared' => [SectionLegendHandler::class, 'section', '   ', null],
            'a group, renamed'   => [GroupLegendHandler::class, 'group', 'Contact', 'Contact'],
            'a group, cleared'   => [GroupLegendHandler::class, 'group', '', null],
        ];
    }

    #[Test]
    public function goesBackToTheFieldsScreenForAPage(): void
    {
        $ids    = $this->layOut();
        $result = $this->page(
            GroupLegendHandler::class,
            'POST',
            ['form' => $ids['form'], 'group' => $ids['group']],
            ['legend' => 'Contact'],
        );

        static::assertSame([ResultKind::Redirect, [], 'Contact'], [
            $result->kind,
            $result->messages,
            $this->legend($ids, 'group'),
        ]);
    }

    #[Test]
    public function renamesNothingAskedWithoutPosting(): void
    {
        $ids    = $this->layOut();
        $result = $this->answer(SectionLegendHandler::class, 'GET', [
            'form'    => $ids['form'],
            'section' => $ids['section'],
            'legend'  => 'Got',
        ]);

        static::assertSame([404, 'About you'], [$result->status, $this->legend($ids, 'section')]);
    }

    #[Test]
    public function renamesNothingOfAnotherForm(): void
    {
        $ids    = $this->layOut();
        $other  = $this->layOut('Newsletter', 'newsletter');
        $result = $this->answer(
            SectionLegendHandler::class,
            'POST',
            ['form' => $ids['form'], 'section' => $other['section']],
            [
                'legend' => 'Taken',
            ],
        );

        static::assertSame([404, 'About you'], [$result->status, $this->legend($other, 'section')]);
    }

    /**
     * @param class-string $handler
     */
    #[Test]
    #[DataProvider('legendProvider')]
    public function savesTheLegendAndTellsTheScript(
        string $handler,
        string $part,
        string $posted,
        ?string $legend,
    ): void {
        $ids    = $this->layOut();
        $result = $this->answer(
            $handler,
            'POST',
            ['form' => $ids['form'], $part => $ids[$part]],
            ['legend' => $posted],
        );

        static::assertSame([['ok' => true, 'legend' => $legend], $legend], [$result->json, $this->legend($ids, $part)]);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * @param array{form: int, section: int, group: int, rows: list<int>, fields: list<int>} $ids
     */
    private function legend(array $ids, string $part): ?string
    {
        $section = $this->form($ids['form'])?->sections[0];

        return 'section' === $part ? $section?->legend : $section?->groups[0]->legend;
    }
}
