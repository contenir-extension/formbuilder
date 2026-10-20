<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\FormBuilder\Definition\FormDefinition;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_array;
use function is_numeric;
use function is_scalar;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * A form's settings as someone posted them, cleaned as the CMS's built-in
 * Forms module cleaned them: a slug made from the title when it is left
 * empty, unknown choices replaced by the default, and empty text as none.
 */
final readonly class SettingsInput
{
    /**
     * @mago-expect lint:excessive-parameter-list One property per setting; built by fromRequest().
     */
    private function __construct(
        public string $slug,
        public string $title,
        public ?string $description,
        public string $layoutMode,
        public string $submitLabel,
        public string $submitAlignment,
        public ?int $retentionDays,
        public string $status,
        public string $successMode,
        public ?string $redirectUrl,
        public ?string $successTitle,
        public ?string $successMessage,
    ) {}

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $posted = $request->getParsedBody();
        $text   = static fn(string $name): string => trim(
            is_array($posted) && is_scalar($posted[$name] ?? null) ? (string) $posted[$name] : '',
        );
        $days = $text('retention_days');

        return new self(
            self::slug('' === $text('slug') ? $text('title') : $text('slug')),
            $text('title'),
            self::orNull($text('description')),
            self::choice($text('layout_mode'), SettingsForm::LAYOUTS, FormDefinition::LAYOUT_SINGLE),
            '' === $text('submit_label') ? 'Submit' : $text('submit_label'),
            self::choice($text('submit_alignment'), SettingsForm::ALIGNMENTS, 'left'),
            is_numeric($days) ? (int) $days : null,
            self::choice($text('status'), SettingsForm::STATUSES, FormDefinition::STATUS_ACTIVE),
            self::choice($text('success_mode'), SettingsForm::AFTER, FormDefinition::SUCCESS_REDIRECT_REFERRER),
            self::orNull($text('success_redirect_url')),
            self::orNull($text('success_title')),
            self::orNull($text('success_message')),
        );
    }

    /**
     * @param array<string, string> $choices
     */
    private static function choice(string $value, array $choices, string $default): string
    {
        return array_key_exists($value, $choices) ? $value : $default;
    }

    private static function orNull(string $value): ?string
    {
        return '' === $value ? null : $value;
    }

    private static function slug(string $value): string
    {
        return trim(
            (string) preg_replace('/[^a-z0-9]+/', replacement: '-', subject: strtolower($value)),
            characters: '-',
        );
    }

    /**
     * The settings as the form repository saves them.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'slug'             => $this->slug,
            'title'            => $this->title,
            'description'      => $this->description,
            'layout_mode'      => $this->layoutMode,
            'submit_label'     => $this->submitLabel,
            'submit_alignment' => $this->submitAlignment,
            'retention_days'   => $this->retentionDays,
            'status'           => $this->status,
            'settings'         => ['success' => [
                'mode'         => $this->successMode,
                'redirect_url' => $this->redirectUrl,
                'title'        => $this->successTitle,
                'message'      => $this->successMessage,
            ]],
        ];
    }

    /**
     * The settings as the screen shows them when it is drawn again.
     *
     * @return array<string, scalar|null>
     */
    public function values(): array
    {
        return [
            'title'                => $this->title,
            'slug'                 => $this->slug,
            'description'          => $this->description,
            'status'               => $this->status,
            'layout_mode'          => $this->layoutMode,
            'submit_label'         => $this->submitLabel,
            'submit_alignment'     => $this->submitAlignment,
            'retention_days'       => $this->retentionDays,
            'success_mode'         => $this->successMode,
            'success_redirect_url' => $this->redirectUrl,
            'success_title'        => $this->successTitle,
            'success_message'      => $this->successMessage,
        ];
    }
}
