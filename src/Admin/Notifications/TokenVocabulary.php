<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Notifications;

use Contenir\FormBuilder\Definition\FormDefinition;

/**
 * The merge tags the token picker offers, grouped: each of the form's
 * fields but hidden ones, by name, and a placeholder for any other; the
 * form's, the entry's and the site's.
 */
final class TokenVocabulary
{
    /**
     * @return list<array{label: string, items: list<array{tag: string, description: string}>}>
     */
    public static function of(FormDefinition $form): array
    {
        $fields = [];
        foreach ($form->getAllFields() as $field) {
            if ('hidden' === $field->type) {
                continue;
            }

            $fields[] = [
                'tag'         => "{field:{$field->name}}",
                'description' => null === $field->label || '' === $field->label ? 'Submitted value' : $field->label,
            ];
        }

        $fields[] = ['tag' => '{field:<name>}', 'description' => 'Any other field — replace <name>'];

        $files = [];
        foreach ($form->getAllFields() as $field) {
            if ('file' !== $field->type) {
                continue;
            }

            $files[] = [
                'tag'         => "{file:{$field->name}}",
                'description' => 'Link to '
                    . (null === $field->label || '' === $field->label ? 'the upload' : $field->label)
                    . ', for 7 days',
            ];
        }

        return [
            ['label' => 'Fields', 'items' => $fields],
            ...([] === $files ? [] : [['label' => 'Files', 'items' => $files]]),
            [
                'label' => 'Form',
                'items' => [
                    ['tag' => '{form:title}', 'description' => 'Form title'],
                    ['tag' => '{form:slug}', 'description' => 'Form slug'],
                    ['tag' => '{form:description}', 'description' => 'Form description'],
                ],
            ],
            [
                'label' => 'Entry',
                'items' => [
                    ['tag' => '{entry:id}', 'description' => 'Entry ID'],
                    ['tag' => '{entry:date}', 'description' => 'Submission timestamp'],
                    ['tag' => '{entry:ip}', 'description' => 'Submitter IP address'],
                    ['tag' => '{entry:status}', 'description' => 'Entry status'],
                    ['tag' => '{entry:fields}', 'description' => 'All fields as an HTML table'],
                ],
            ],
            [
                'label' => 'Site',
                'items' => [
                    ['tag' => '{site:admin_url}', 'description' => 'CMS admin URL'],
                    ['tag' => '{site:base_url}', 'description' => 'Site base URL'],
                ],
            ],
        ];
    }
}
