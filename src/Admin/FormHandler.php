<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\Screen;
use Contenir\Extension\Api\Operation\Operation;
use Contenir\Extension\Formbuilder\Persistence\Forms;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Persistence\PersistenceException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A form's Settings tab: its details, display and behaviour, and what
 * happens after submission, as a described screen. Saving checks them as the
 * built-in module did and says what stopped it; Delete form, which needs
 * Delete, asks first.
 */
final class FormHandler implements HandlerInterface
{
    public const string NAME = FormTabs::SETTINGS;

    /**
     * @param array<string, scalar|null> $values
     */
    private static function screen(FormDefinition $form, int $id, array $values): HandlerResult
    {
        return HandlerResult::screen(new Screen(
            $form->title,
            'How the form is named, shown and finished.',
            SettingsForm::of($values),
            operations: [new Operation(
                'delete',
                'Delete form',
                'trash',
                'delete',
                DeleteFormOperation::class,
                confirm: true,
                confirmMessage: 'Delete this form, its fields, notifications, integrations and every entry? This cannot be undone.',
            )],
            tabs: FormTabs::of($id, self::NAME),
        ));
    }

    /**
     * @throws PersistenceException        When the form can't be read or saved.
     * @throws ContainerExceptionInterface When the host can't give the site's database.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $forms = Forms::of($context);
        $form  = $forms->loader->loadById(FormId::of($request));
        $id    = $form?->id;
        if (null === $form || null === $id) {
            return FormId::missing();
        }

        if ('POST' !== $request->getMethod()) {
            return self::screen($form, $id, SettingsForm::values($form));
        }

        $input    = SettingsInput::fromRequest($request);
        $problems = (new SettingsCheck($forms->repository))->problems($input, $id);
        if ([] !== $problems) {
            $result = self::screen($form, $id, $input->values());
            foreach ($problems as $problem) {
                $result = $result->withMessage('danger', $problem);
            }

            return $result;
        }

        $forms->repository->updateForm($id, $input->data());

        return HandlerResult::redirect(Urls::handler(self::NAME, ['form' => $id]))->withMessage(
            'success',
            'Form updated.',
        );
    }
}
