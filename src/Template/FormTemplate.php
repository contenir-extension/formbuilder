<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Template;

/**
 * A pre-baked form-builder seed.
 *
 * Templates are admin-side conveniences for quick-creating common
 * form shapes (Contact, Newsletter Signup, Product Enquiry, ...).
 * The shape mirrors the persisted form aggregate — sections →
 * groups → rows → fields — but as a plain PHP array so authors
 * can declare new templates without DB writes.
 *
 * Adding a form from a template walks the array and writes real rows
 * through the form repository, leaving the editor on the fields editor
 * with the structure already in place.
 *
 * @phpstan-type FieldSeed array{
 *     type: string,
 *     name: string,
 *     label?: ?string,
 *     show_label?: bool,
 *     description?: ?string,
 *     placeholder?: ?string,
 *     default_value?: ?string,
 *     required?: bool,
 *     options?: array<string, mixed>,
 *     validators?: list<array<string, mixed>>,
 * }
 * @phpstan-type RowSeed array{fields: list<FieldSeed>}
 * @phpstan-type GroupSeed array{legend?: ?string, rows: list<RowSeed>}
 * @phpstan-type SectionSeed array{key: string, legend?: ?string, groups: list<GroupSeed>}
 */
final class FormTemplate
{
    /**
     * @param list<SectionSeed> $sections
     *
     * @mago-expect lint:excessive-parameter-list Immutable value object, built with named arguments.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly string $icon,
        public readonly string $titleSuggestion,
        public readonly string $slugSuggestion,
        public readonly array $sections,
    ) {}
}
