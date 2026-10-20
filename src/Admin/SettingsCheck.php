<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Contenir\FormBuilder\Repository\FormRepositoryInterface;

use function preg_match;
use function str_starts_with;

/**
 * What stops a form's settings saving, in the CMS's built-in Forms module's
 * words: a missing title, a slug that is empty once made safe or is taken, a retention
 * period that isn't positive, or an After submission choice missing what it
 * needs.
 */
final readonly class SettingsCheck
{
    public function __construct(
        private FormRepositoryInterface $forms,
    ) {}

    private static function afterProblem(SettingsInput $input): ?string
    {
        $url      = $input->redirectUrl ?? '';
        $redirect = FormDefinition::SUCCESS_REDIRECT_URL === $input->successMode;
        if ($redirect && '' === $url) {
            return 'A URL is required when redirecting to a custom URL.';
        }

        if ($redirect && ! str_starts_with($url, '/') && 1 !== preg_match('#^https?://#i', $url)) {
            return 'URL must be http(s) or a site-relative path (start with /).';
        }

        return FormDefinition::SUCCESS_INLINE_MESSAGE === $input->successMode && null === $input->successMessage
            ? 'A confirmation message is required when showing an inline message.'
            : null;
    }

    /**
     * @param int|null $formId The form being edited, whose own slug it may keep; null for a new form
     *
     * @return list<string>
     *
     * @throws PersistenceException When the slugs taken can't be read.
     */
    public function problems(SettingsInput $input, ?int $formId): array
    {
        $problems = [];
        if ('' === $input->title) {
            $problems[] = 'Title is required.';
        }

        if ('' === $input->slug) {
            $problems[] = 'Slug must be lowercase letters, numbers and hyphens.';
        }

        if ('' !== $input->slug && $this->forms->isSlugTaken($input->slug, $formId)) {
            $problems[] = 'Slug is already in use.';
        }

        if (null !== $input->retentionDays && $input->retentionDays < 1) {
            $problems[] = 'Retention must be a positive number of days, or empty.';
        }

        $after = self::afterProblem($input);

        return null === $after ? $problems : [...$problems, $after];
    }
}
