<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Contract;

use Contenir\Extension\Formbuilder\Admin\Fields\FieldEditHandler;
use Contenir\Extension\Formbuilder\Admin\Fields\FieldsHandler;
use Contenir\Extension\Formbuilder\Admin\Notifications\NotificationLayoutHandler;
use Contenir\Extension\Formbuilder\Admin\Preview\PreviewHandler;
use Contenir\Extension\Formbuilder\Tests\Trait\FieldsEditorTrait;
use DOMDocument;
use DOMXPath;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function libxml_use_internal_errors;
use function preg_replace;
use function sprintf;

use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;

/**
 * The markup the add-on's scripts are tested against (tests/js/fixtures),
 * drawn here from its templates so the two sides cannot drift apart
 * unnoticed. A change to the markup fails this test until the fixtures are
 * regenerated with UPDATE_CONTRACT_FIXTURES=1, which puts the change in
 * front of the JS tests and the reviewer.
 */
#[Group('integration')]
#[Group('contract')]
final class ComponentFixtureTest extends TestCase
{
    use FieldsEditorTrait;

    /**
     * @return array<string, array{string, class-string, array<string, int>, string}>
     */
    public static function fixtureProvider(): array
    {
        return [
            'the fields editor, three sections of groups, rows and fields' => [
                'fields-editor-form',
                FieldsHandler::class,
                ['form' => 501],
                '//div[@class="builder"]',
            ],
            'the stepper, in the preview of a stepped form'                => [
                'form-stepper-preview',
                PreviewHandler::class,
                ['form' => 501],
                '//form[@data-form-stepper]',
            ],
            'the notification layout, editor beside its preview'           => [
                'notification-layout',
                NotificationLayoutHandler::class,
                ['form' => 501, 'notification' => 951],
                '//form[@data-notification-layout]',
            ],
            'the conditional editor, in a field whose form has others'     => [
                'conditional-editor-field',
                FieldEditHandler::class,
                ['form' => 501, 'field' => 901],
                '//*[@data-conditional-editor]',
            ],
        ];
    }

    /**
     * @param class-string          $handler
     * @param array<string, int>    $query
     */
    #[Test]
    #[DataProvider('fixtureProvider')]
    public function keepsTheScriptsFixtureInStepWithTheTemplates(
        string $fixture,
        string $handler,
        array $query,
        string $element,
    ): void {
        $html = "{$this->element($this->drawn($this->page($handler, 'GET', $query)), $element)}\n";
        $file = sprintf('%s/js/fixtures/components/%s.html', dirname(__DIR__, levels: 2), $fixture);
        if ('1' === getenv('UPDATE_CONTRACT_FIXTURES')) {
            file_put_contents($file, $html);
        }

        static::assertSame(
            (string) file_get_contents($file),
            $html,
            "tests/js/fixtures/components/{$fixture}.html no longer matches the templates; regenerate it with UPDATE_CONTRACT_FIXTURES=1 and check the JS tests",
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
        $this->seed();
    }

    /**
     * The first element the path finds, as its markup, with a form's
     * anti-forgery token, which changes on every draw, made a fixed value.
     */
    private function element(string $html, string $path): string
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            "<?xml encoding=\"UTF-8\"><div>{$html}</div>",
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_use_internal_errors($previous);
        $node = (new DOMXPath($document))->query($path)
            ?->item(0);

        $markup = null === $node ? '' : (string) $document->saveHTML($node);

        return (string) preg_replace('/(name="_csrf" value=")[^"]*"/', replacement: '$1token"', subject: $markup);
    }

    /**
     * The form the fixtures show, with the ids the JS tests name: three
     * sections of one group each, the first group holding two rows, and four
     * fields.
     */
    private function seed(): void
    {
        $this->database->query(
            'INSERT INTO form (form_id, slug, title, status, layout_mode, submit_label) VALUES (?, ?, ?, ?, ?, ?)',
            [501, 'enquiry', 'Enquiry', 'active', 'stepped', 'Submit'],
        );
        foreach ([
            [1, 'about',   'About you'],
            [2, 'details', 'Your enquiry'],
            [3, 'review',  'Review'],
        ] as [$n, $key, $legend]) {
            $this->database->query('INSERT INTO form_section (form_section_id, form_id, key, legend, sort) VALUES (?, 501, ?, ?, ?)', [
                600 + $n,
                $key,
                $legend,
                $n,
            ]);
            $this->database->query('INSERT INTO form_group (form_group_id, form_section_id, legend, sort) VALUES (?, ?, ?, 0)', [
                700 + $n,
                600 + $n,
                $legend,
            ]);
            $this->database->query('INSERT INTO form_row (form_row_id, form_group_id, sort) VALUES (?, ?, 0)', [
                800 + $n,
                700 + $n,
            ]);
        }

        $this->database->query('INSERT INTO form_row (form_row_id, form_group_id, sort) VALUES (?, ?, ?)', [
            804,
            701,
            1,
        ]);
        $this->database->query(
            'INSERT INTO form_notification (form_notification_id, form_id, name, to_address, subject, body_template) VALUES (?, ?, ?, ?, ?, ?)',
            [951, 501, 'Team', 'team@example.com', 'New enquiry', '<p>Hello {field:name}</p>'],
        );
        foreach ([
            [901, 801, 'text',     'name',      'Name',      0, 1],
            [902, 801, 'email',    'email',     'Email',     1, 0],
            [903, 802, 'textarea', 'message',   'Message',   0, 0],
            [904, 803, 'text',     'reference', 'Reference', 0, 0],
        ] as $field) {
            $this->database->query(
                'INSERT INTO form_field (form_field_id, form_row_id, type, name, label, sort, required) VALUES (?, ?, ?, ?, ?, ?, ?)',
                $field,
            );
        }
    }
}
