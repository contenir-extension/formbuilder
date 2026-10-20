<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\Screen;
use Contenir\Extension\Formbuilder\Persistence\Forms;
use Contenir\Extension\Formbuilder\Template\FormFromTemplate;
use Contenir\Extension\Formbuilder\Template\FormTemplate;
use Contenir\Extension\Formbuilder\Template\FormTemplateRegistry;
use Contenir\FormBuilder\Persistence\PersistenceException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * Add form: a new form's settings as a described screen, started from a
 * template when ?template= names one. Saving creates the form with the
 * template's layout, or an empty one, and opens its fields editor.
 */
final class AddFormHandler implements HandlerInterface
{
    public const string NAME = 'form-add';

    /**
     * @param array<string, scalar|null> $values
     */
    private static function screen(?FormTemplate $template, array $values): HandlerResult
    {
        return HandlerResult::screen(new Screen(
            'Add form',
            null === $template
                ? 'Name the form and choose how it behaves. You add its fields next.'
                : "Starting from the {$template->name} template: its fields are added for you.",
            SettingsForm::of($values),
        ));
    }

    private static function template(ServerRequestInterface $request): ?FormTemplate
    {
        $query = $request->getQueryParams();

        return is_string($query['template'] ?? null) ? (new FormTemplateRegistry())->get($query['template']) : null;
    }

    /**
     * @throws PersistenceException        When the form can't be created.
     * @throws ContainerExceptionInterface When the host can't give the site's database.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $template = self::template($request);
        if ('POST' !== $request->getMethod()) {
            return self::screen($template, SettingsForm::blank($template));
        }

        $forms    = Forms::of($context);
        $input    = SettingsInput::fromRequest($request);
        $problems = (new SettingsCheck($forms->repository))->problems($input, null);
        if ([] !== $problems) {
            $result = self::screen($template, $input->values());
            foreach ($problems as $problem) {
                $result = $result->withMessage('danger', $problem);
            }

            return $result;
        }

        $formId = $forms->repository->createForm($input->data());
        (new FormFromTemplate($forms->repository))->build($formId, $template);

        return HandlerResult::redirect(Urls::handler(FormTabs::FIELDS, ['form' => $formId]))->withMessage(
            'success',
            null === $template ? 'Form created.' : "Form created from \"{$template->name}\" template.",
        );
    }
}
