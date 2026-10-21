<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\FormBuilder\FieldType\FieldTypeInterface;
use Contenir\FormBuilder\FieldType\FieldTypeRegistry;

use function html_entity_decode;
use function ucfirst;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * The field types an editor may pick, as the fields editor names and draws
 * them: each one's label, decoded once (a type gives "Date &amp; time" and
 * the templates escape it themselves), and its icon.
 */
final readonly class FieldTypes
{
    private function __construct(
        private FieldTypeRegistry $registry,
    ) {}

    public static function selectable(): self
    {
        return new self(new FieldTypeRegistry());
    }

    /**
     * @return list<FieldTypeInterface>
     */
    public function all(): array
    {
        return $this->registry->userSelectable();
    }

    public function has(string $key): bool
    {
        return $this->registry->has($key);
    }

    public function icon(string $key): string
    {
        foreach ($this->all() as $type) {
            if ($type->key() === $key) {
                return $type->icon();
            }
        }

        return 'square';
    }

    public function label(string $key): string
    {
        foreach ($this->all() as $type) {
            if ($type->key() === $key) {
                return html_entity_decode($type->label(), ENT_QUOTES | ENT_HTML5, encoding: 'UTF-8');
            }
        }

        return ucfirst($key);
    }
}
