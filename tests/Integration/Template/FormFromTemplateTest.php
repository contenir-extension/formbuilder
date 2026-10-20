<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Template;

use Contenir\Extension\Formbuilder\Template\FormFromTemplate;
use Contenir\Extension\Formbuilder\Template\FormTemplate;
use Contenir\Extension\Formbuilder\Tests\Trait\FormsDatabaseTrait;
use Contenir\FormBuilder\Loader\PhpDbFormLoader;
use Contenir\FormBuilder\Repository\PhpDbFormRepository;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A new form's layout written from a template, legends and all.
 */
#[Group('integration')]
final class FormFromTemplateTest extends TestCase
{
    use FormsDatabaseTrait;

    #[Test]
    public function writesTheTemplatesSectionsAndGroupsWithTheirLegends(): void
    {
        $id = $this->addForm('Enrolment', 'enrolment');
        (new FormFromTemplate(new PhpDbFormRepository($this->database)))->build($id, new FormTemplate(
            slug: 'enrolment',
            name: 'Enrolment',
            description: 'Sign up for a course.',
            icon: 'school',
            titleSuggestion: 'Enrolment',
            slugSuggestion: 'enrolment',
            sections: [[
                'key'    => 'student',
                'legend' => 'About you',
                'groups' => [[
                    'legend' => 'Your name',
                    'rows'   => [['fields' => [['type' => 'text', 'name' => 'given_name', 'label' => 'Given name']]]],
                ]],
            ]],
        ));
        $section = (new PhpDbFormLoader($this->database))->loadById($id)?->sections[0] ?? null;

        static::assertSame(
            ['student', 'About you', 'Your name', 'given_name', 'Given name'],
            [
                $section?->key,
                $section?->legend,
                $section?->groups[0]->legend ?? null,
                $section?->groups[0]->rows[0]->fields[0]->name ?? null,
                $section?->groups[0]->rows[0]->fields[0]->label ?? null,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }
}
