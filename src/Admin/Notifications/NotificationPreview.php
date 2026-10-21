<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Service\TokenReplacer;
use DateTimeImmutable;

use function htmlspecialchars;
use function preg_split;
use function str_contains;
use function strrpos;

use const ENT_QUOTES;
use const PREG_SPLIT_DELIM_CAPTURE;

/**
 * A notification's body as its Layout tab previews it: filled in with
 * recognisable sample values for the form's fields and a sample entry, each
 * merge tag it recognised marked so the editor can see what was filled in.
 * An unrecognised tag is left as it is, as the email would leave it; a tag
 * that fills in markup (the entry's fields table) or sits inside an HTML
 * tag is filled in unmarked, so the marking can't break the markup.
 */
final readonly class NotificationPreview
{
    private const string MERGE_TAG = '/(\{[a-z]+:[a-z0-9_\-\.]+\})/i';

    public function __construct(
        private TokenReplacer $tags,
        private DateTimeImmutable $now,
    ) {}

    /**
     * Whether what follows $before is inside an HTML tag: the last < in it
     * has no > after it.
     */
    private static function insideTag(string $before): bool
    {
        $open  = strrpos($before, needle: '<');
        $close = strrpos($before, needle: '>');

        return false !== $open && (false === $close || $open > $close);
    }

    /**
     * @mago-expect analysis:possibly-false-iterator preg_split fails only on a malformed pattern, and MERGE_TAG is a constant that compiles.
     */
    public function body(string $template, FormDefinition $form): string
    {
        if ('' === $template) {
            return '<p class="preview-empty">Body template is empty.</p>';
        }

        $values = [];
        foreach ($form->getAllFields() as $field) {
            $values[$field->name] = $this->sample($field);
        }

        $entry  = ['id' => 0, 'date' => $this->now->format('Y-m-d H:i:s'), 'ip' => '127.0.0.1', 'status' => 'pending'];
        $output = '';
        $before = '';
        foreach (preg_split(self::MERGE_TAG, $template, flags: PREG_SPLIT_DELIM_CAPTURE) as $piece) {
            $output .= $this->marked($piece, $this->tags->replace($piece, $form, $values, $entry), $before);
            $before .= $piece;
        }

        return $output;
    }

    /**
     * $filled, what $piece filled in, marked when $piece is a merge tag that
     * was recognised and the mark can't break the markup. Text between tags
     * fills in as itself.
     */
    private function marked(string $tag, string $filled, string $before): string
    {
        if ($filled === $tag || str_contains($filled, needle: '<') || self::insideTag($before)) {
            return $filled;
        }

        return (
            '<span class="token-replaced" title="'
                . htmlspecialchars($tag, ENT_QUOTES, encoding: 'UTF-8')
                . "\">{$filled}</span>"
        );
    }

    /**
     * A plausible value for the field, plainly a sample.
     */
    private function sample(FieldDefinition $field): string
    {
        return match ($field->type) {
            'email'                          => 'alex@example.com',
            'tel'                            => '+61 4 0000 0000',
            'url'                            => 'https://example.com',
            'number'                         => '42',
            'date'                           => $this->now->format('Y-m-d'),
            'datetime'                       => $this->now->format('Y-m-d H:i'),
            'time'                           => $this->now->format('H:i'),
            'checkbox'                       => '1',
            'textarea'                       => "Sample multi-line content.\nLine two for visual rhythm.",
            'file'                           => 'uploads/sample.pdf',
            'hidden'                         => 'hidden-value',
            'select', 'radio', 'multiselect' => 'Option A',
            default                          => "Sample {$field->name}",
        };
    }
}
