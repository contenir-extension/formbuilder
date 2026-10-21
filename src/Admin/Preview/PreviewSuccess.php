<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Preview;

use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Service\TokenReplacer;

use function in_array;
use function is_array;
use function is_string;

/**
 * What the live form would do after a valid test submission, as its
 * After submission settings say, with the submission's values in their
 * tokens: reload the page that hosted it, go to a URL, or show a message in
 * its place.
 */
final readonly class PreviewSuccess
{
    private function __construct(
        public string $mode,
        public ?string $redirectUrl,
        public ?string $title,
        public ?string $message,
    ) {}

    /**
     * @param array<string, mixed> $values The submission's values
     */
    public static function of(FormDefinition $form, array $values, TokenReplacer $tokens): self
    {
        $stored  = is_array($form->settings['success'] ?? null) ? $form->settings['success'] : [];
        $mode    = self::text($stored, 'mode');
        $url     = self::text($stored, 'redirect_url');
        $title   = self::text($stored, 'title');
        $message = self::text($stored, 'message');
        $entry   = ['id' => 0];

        return new self(
            null !== $mode && in_array($mode, FormDefinition::SUCCESS_MODES, strict: true)
                ? $mode
                : FormDefinition::SUCCESS_REDIRECT_REFERRER,
            null !== $url && '' !== $url ? $tokens->replaceForUrl($url, $form, $values, $entry) : null,
            null === $title ? null : $tokens->replace($title, $form, $values, $entry),
            null === $message ? null : $tokens->replace($message, $form, $values, $entry),
        );
    }

    /**
     * @param array<array-key, mixed> $stored
     */
    private static function text(array $stored, string $key): ?string
    {
        return is_string($stored[$key] ?? null) ? $stored[$key] : null;
    }
}
