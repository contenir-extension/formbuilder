<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Fields;

use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Contenir\FormBuilder\Conditional\RuleEvaluator;
use Contenir\FormBuilder\Validator\ValidatorFactory;

/**
 * A field's settings form, for adding or editing one: in the fields
 * editor's modal for its script, or as a page of its own for a browser
 * without scripts, with the form's tabs. Saving sends both back to the
 * fields screen, saying so.
 */
final readonly class FieldForm
{
    /**
     * @param string                $handler The handler the form posts to
     * @param array<string, scalar> $query   What it posts to it with, besides the form
     * @param ?int                  $field   The field edited, which its conditions can't refer to
     */
    public function __construct(
        private string $handler,
        private array $query,
        private string $title,
        private string $submit,
        private ?int $field = null,
    ) {}

    /**
     * Back to the fields screen, saying $message: the script is told where
     * to go, a page is sent there.
     */
    public static function saved(Editor $editor, string $message): HandlerResult
    {
        return $editor->scripted()
            ? HandlerResult::json([
                'ok'       => true,
                'redirect' => Urls::handler(FormTabs::FIELDS, ['form' => $editor->layout->id]),
            ])->withMessage('success', $message)
            : $editor->back($message);
    }

    /**
     * The form showing $values, and $errors with them, by the setting at
     * fault.
     *
     * @param array<string, mixed>  $values
     * @param array<string, string> $errors
     */
    public function show(Editor $editor, FieldTypes $types, array $values, array $errors = []): HandlerResult
    {
        $layout = $editor->layout;
        $shown  = [
            'form'       => $layout->form,
            'field'      => $values,
            'errors'     => $errors,
            'types'      => $types,
            'validators' => ValidatorFactory::vocabulary(),
            'operators'  => RuleEvaluator::operatorVocabulary(),
            'siblings'   => $layout->siblings($this->field),
            'handler'    => $this->handler,
            'query'      => ['form' => $layout->id] + $this->query,
            'title'      => $this->title,
            'submit'     => $this->submit,
        ];

        if ($editor->scripted()) {
            return HandlerResult::fragment('forms/fields/modal', $shown, [] === $errors ? 200 : 422);
        }

        return HandlerResult::template(
            'forms/field',
            $this->title,
            $shown,
            FormTabs::of($layout->id, FormTabs::FIELDS),
        );
    }
}
