<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\FormBuilder\Conditional\ConditionalRulesParser;

use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function is_array;
use function preg_match;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * A field's settings as the field form posts them, made into what the form
 * builder stores, as the built-in module read them: its ID made a slug, its
 * options and rules cleaned, empty settings left out. Its width isn't
 * posted (the editor balances a row's fields itself), nor its place in the
 * row, unless a sort is posted: a new field goes at the row's end and an
 * edited one stays where it is, where the built-in module put both first.
 */
final readonly class FieldInput
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        public string $name,
        public array $data,
    ) {}

    /**
     * @param array<array-key, mixed> $body
     */
    public static function fromBody(array $body, FieldTypes $types): self
    {
        $posted = static fn(string $name): mixed => $body[$name] ?? null;
        $type   = Posted::text($posted('type'));
        $type   = $types->has($type) ? $type : 'text';
        $name   = self::slug(Posted::text($posted('name')));

        return new self($name, [
            'type'          => $type,
            'name'          => $name,
            'label'         => Posted::optional($posted('label')),
            'show_label'    => Posted::ticked($posted('show_label')),
            'description'   => Posted::optional($posted('description')),
            'placeholder'   => Posted::optional($posted('placeholder')),
            'default_value' => self::defaultValue($posted('default_value')),
            'required'      => Posted::ticked($posted('required')),
            'options'       => FieldOptions::of($type, Posted::items($posted('options') ?? [])),
            'validators'    => FieldRules::of($posted('validators')),
            'filters'       => self::filled(Posted::items($posted('filters') ?? [])),
            'conditional'   => ConditionalRulesParser::parse($posted('conditional')),
        ] + (null === $posted('sort') ? [] : ['sort' => Posted::number($posted('sort'))]));
    }

    /**
     * A choice field's defaults post as a list and are stored comma-joined;
     * any other default is one value.
     */
    private static function defaultValue(mixed $posted): ?string
    {
        if (! is_array($posted)) {
            return Posted::optional($posted);
        }

        $values = self::filled($posted);

        return [] === $values ? null : implode(',', $values);
    }

    /**
     * The values in $posted that aren't empty, as trimmed text.
     *
     * @param array<array-key, mixed> $posted
     *
     * @return list<string>
     */
    private static function filled(array $posted): array
    {
        return array_values(array_filter(
            array_map(Posted::text(...), $posted),
            static fn(string $value): bool => '' !== $value,
        ));
    }

    /**
     * $value as lowercase letters and digits joined by single dashes.
     */
    private static function slug(string $value): string
    {
        return trim(
            (string) preg_replace('/[^a-z0-9]+/', replacement: '-', subject: strtolower($value)),
            characters: '-',
        );
    }

    /**
     * What stops the field saving, by the setting at fault.
     *
     * @return array<string, string>
     */
    public function problems(): array
    {
        return (
            1 === preg_match('/^[a-z][a-z0-9_\-]*$/', $this->name)
                ? []
                : [
                    'name' => 'Field ID must start with a letter and contain only lowercase letters, numbers, dashes and underscores.',
                ]
        );
    }

    /**
     * The same settings with the stored type in place of the posted one: a
     * field's type is fixed once it is made.
     */
    public function withType(string $type): self
    {
        return new self($this->name, ['type' => $type] + $this->data);
    }
}
