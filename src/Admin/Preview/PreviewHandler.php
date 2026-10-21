<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Preview;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Mail\MailerInterface;
use Contenir\Extension\Formbuilder\Admin\FormId;
use Contenir\Extension\Formbuilder\Admin\FormTabs;
use Contenir\Extension\Formbuilder\Admin\SiteTokens;
use Contenir\Extension\Formbuilder\Persistence\Forms;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Delivery\ImmediateDelivery;
use Contenir\FormBuilder\FieldType\FieldTypeRegistry;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Contenir\FormBuilder\Registrar\EmailNotificationRegistrar;
use Contenir\FormBuilder\Render\FormMarkup;
use Contenir\FormBuilder\Service\FormBuilderService;
use Contenir\FormBuilder\Service\FormSubmissionService;
use Contenir\FormBuilder\Service\TokenReplacer;
use Contenir\FormBuilder\Validator\ValidatorFactory;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function array_keys;
use function is_array;
use function is_string;

/**
 * A form's Preview tab: the form as the site renders it, and a test
 * submission. A test runs the live form's checks and sends its
 * notifications with "Test: " before their subjects, through the site's
 * mail when the host lends it; it stores no entry and calls no webhook, so
 * of where it came from only the address its notifications can show is
 * passed on. Its notifications fill {settings:*} tags from the site's
 * settings when the host lends them. After a valid test the tab says what the live form would do
 * next.
 */
final class PreviewHandler implements HandlerInterface
{
    public const string NAME = FormTabs::PREVIEW;

    /**
     * The site's mail, when the host lends it.
     *
     * @throws ContainerExceptionInterface When the host can't give it.
     */
    private static function mail(ExtensionContextInterface $context): ?HostMail
    {
        $services = $context->services();
        $mailer   = $services->has(MailerInterface::class) ? $services->get(MailerInterface::class) : null;

        return $mailer instanceof MailerInterface ? new HostMail($mailer) : null;
    }

    /**
     * What the test posted, by field name.
     *
     * @return array<string, mixed>
     */
    private static function posted(ServerRequestInterface $request): array
    {
        $body   = $request->getParsedBody();
        $body   = is_array($body) ? $body : [];
        $posted = [];
        foreach (array_keys($body) as $name) {
            if (! is_string($name)) {
                continue;
            }

            $posted[$name] = $body[$name] ?? null;
        }

        return $posted;
    }

    private static function screen(
        FormDefinition $form,
        int $id,
        string $markup,
        ?PreviewSuccess $success = null,
        ?CountedMail $mail = null,
    ): HandlerResult {
        return HandlerResult::template(
            'forms/preview',
            $form->title,
            [
                'form'    => $form,
                'markup'  => $markup,
                'success' => $success,
                ...(null === $mail ? [] : ['sent' => $mail->summary(), 'failed' => $mail->failed]),
            ],
            FormTabs::of($id, self::NAME),
        );
    }

    /**
     * The tokens a test's notifications and success settings fill in, as
     * the built-in module's preview filled them: the admin's and the site's
     * address, and the site's settings when the host lends them.
     *
     * @throws ContainerExceptionInterface When the host can't give its settings.
     */
    private static function tokens(ServerRequestInterface $request, ExtensionContextInterface $context): TokenReplacer
    {
        $uri    = $request->getUri();
        $tokens = new TokenReplacer([
            'admin_url' => '/admin',
            'base_url'  => "{$uri->getScheme()}://{$uri->getAuthority()}",
        ]);
        SiteTokens::register($tokens, $context);

        return $tokens;
    }

    /**
     * @throws PersistenceException        When the form can't be read.
     * @throws ContainerExceptionInterface When the host can't give the site's database or its mail.
     * @throws LogicException              When the host offers no site database.
     * @throws RuntimeException            When an uploaded file can't be read.
     *
     * @mago-expect analysis:unhandled-thrown-type The library builds the form with Laminas, which the add-on can't name; a broken definition fails the handler, which the host reports.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $form = Forms::of($context)->loader->loadById(FormId::of($request));
        $id   = $form?->id;
        if (null === $form || null === $id) {
            return FormId::missing();
        }

        $builder = new FormBuilderService(new FieldTypeRegistry(), new ValidatorFactory());
        if ('POST' !== $request->getMethod()) {
            return self::screen($form, $id, (new FormMarkup())->render($form, $builder->build($form), preview: true));
        }

        $tokens  = self::tokens($request, $context);
        $mail    = new CountedMail(self::mail($context));
        $service = new FormSubmissionService($builder);
        $service->attach(new EmailNotificationRegistrar(
            $tokens,
            new ImmediateDelivery($mail, log: $context->logger()),
            $context->logger(),
            'Test: ',
        ));

        $result = $service->submit($form, self::posted($request), UploadedFiles::of($request), [
            'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? '',
        ]);

        return $result->valid
            ? self::screen($form, $id, '', PreviewSuccess::of($form, $result->values, $tokens), $mail)
            : self::screen($form, $id, (new FormMarkup())->render($form, $result->form, preview: true));
    }
}
