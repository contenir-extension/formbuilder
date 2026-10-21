<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\FormBuilder\Definition\FieldDefinition;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Definition\GroupDefinition;
use Contenir\FormBuilder\Definition\RowDefinition;
use Contenir\FormBuilder\Definition\SectionDefinition;

use function array_column;
use function array_merge;

/**
 * A form's sections, groups, rows and fields, found by id. The editor's
 * handlers change only parts they find here, so an id from another form
 * changes nothing.
 */
final readonly class FormLayout
{
    /**
     * @param array<int, SectionDefinition> $sections
     * @param array<int, GroupDefinition>   $groups
     * @param array<int, RowDefinition>     $rows
     * @param array<int, FieldDefinition>   $fields
     *
     * @mago-expect lint:excessive-parameter-list Built only by of(), with each kind of part indexed by id.
     */
    private function __construct(
        public FormDefinition $form,
        public int $id,
        private array $sections,
        private array $groups,
        private array $rows,
        private array $fields,
    ) {}

    public static function of(FormDefinition $form, int $id): self
    {
        $groups = array_merge(...array_column($form->sections, 'groups'));
        $rows   = array_merge(...array_column($groups, 'rows'));

        return new self(
            $form,
            $id,
            self::byId($form->sections),
            self::byId($groups),
            self::byId($rows),
            self::byId($form->getAllFields()),
        );
    }

    /**
     * @template T of SectionDefinition|GroupDefinition|RowDefinition|FieldDefinition
     *
     * @param list<T> $parts
     *
     * @return array<int, T>
     */
    private static function byId(array $parts): array
    {
        $byId = [];
        foreach ($parts as $part) {
            $byId[(int) $part->id] = $part;
        }

        return $byId;
    }

    public function field(int $id): ?FieldDefinition
    {
        return $this->fields[$id] ?? null;
    }

    public function group(int $id): ?GroupDefinition
    {
        return $this->groups[$id] ?? null;
    }

    public function row(int $id): ?RowDefinition
    {
        return $this->rows[$id] ?? null;
    }

    public function section(int $id): ?SectionDefinition
    {
        return $this->sections[$id] ?? null;
    }

    /**
     * The other named fields a field's conditions and date limits may refer
     * to, by name, label (or name, without one) and type; never the field
     * itself.
     *
     * @return list<array{name: string, label: string, type: string}>
     */
    public function siblings(?int $except): array
    {
        $siblings = [];
        foreach ($this->fields as $field) {
            if ($field->id === $except || '' === $field->name) {
                continue;
            }

            $siblings[] = [
                'name'  => $field->name,
                'label' => null !== $field->label && '' !== $field->label ? $field->label : $field->name,
                'type'  => $field->type,
            ];
        }

        return $siblings;
    }
}
