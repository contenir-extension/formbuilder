<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Template;

use Contenir\FormBuilder\Persistence\PersistenceException;
use Contenir\FormBuilder\Repository\FormRepositoryInterface;

/**
 * Builds a new form's layout: from a template, walking its sections, groups,
 * rows and fields and writing each, or else one empty section, group and row
 * for the fields editor to fill.
 */
final readonly class FormFromTemplate
{
    private const array BLANK_FIELD = [
        'type'          => 'text',
        'name'          => '',
        'label'         => null,
        'show_label'    => true,
        'description'   => null,
        'placeholder'   => null,
        'default_value' => null,
        'required'      => false,
        'sort'          => 0,
        'options'       => [],
        'validators'    => [],
        'filters'       => [],
        'conditional'   => null,
    ];

    public function __construct(
        private FormRepositoryInterface $forms,
    ) {}

    /**
     * @throws PersistenceException When a part can't be written.
     */
    public function build(int $formId, ?FormTemplate $template): void
    {
        if (null === $template) {
            $this->forms->createRow($this->forms->createGroup($this->forms->createSection($formId, [
                'key' => 'main',
            ]), []));

            return;
        }

        foreach ($template->sections as $section) {
            $sectionId = $this->forms->createSection($formId, [
                'key'    => $section['key'],
                'legend' => $section['legend'] ?? null,
            ]);
            foreach ($section['groups'] as $group) {
                $groupId = $this->forms->createGroup($sectionId, ['legend' => $group['legend'] ?? null]);
                foreach ($group['rows'] as $row) {
                    $rowId = $this->forms->createRow($groupId);
                    foreach ($row['fields'] as $field) {
                        $this->forms->createField($rowId, $field + self::BLANK_FIELD);
                    }
                }
            }
        }
    }
}
