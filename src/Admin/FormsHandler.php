<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ParentPage;
use Contenir\Extension\Formbuilder\Persistence\SiteDatabase;
use Contenir\Extension\Formbuilder\Template\FormTemplateRegistry;
use Contenir\FormBuilder\Definition\FormDefinition;
use Contenir\FormBuilder\Loader\PhpDbFormLoader;
use Contenir\FormBuilder\Persistence\PersistenceException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;

/**
 * The Forms area's home: a card per form, with its status, slug and layout,
 * its title leading to its entries and its footer to each of its screens,
 * and Add form offering the templates to start from. Drawn from a template,
 * since a card per record can't be described.
 */
final class FormsHandler implements HandlerInterface
{
    public const string NAME = 'forms';

    /**
     * The Forms home as the parent page of a screen about every form, such
     * as a tool, so the breadcrumb reads Forms › the tool.
     */
    public static function parent(): ParentPage
    {
        return new ParentPage('Forms', self::NAME);
    }

    /**
     * What a form's card shows.
     *
     * @return array{id: int|null, title: string, slug: string, active: bool, layout: string}
     */
    private static function card(FormDefinition $form): array
    {
        return [
            'id'     => $form->id,
            'title'  => $form->title,
            'slug'   => $form->slug,
            'active' => FormDefinition::STATUS_ACTIVE === $form->status,
            'layout' => $form->layoutMode,
        ];
    }

    /**
     * @throws PersistenceException        When the forms can't be read.
     * @throws ContainerExceptionInterface When the host can't give the site's database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        return HandlerResult::template('forms/index', 'Forms', [
            'forms'     => array_map(self::card(...), (new PhpDbFormLoader(SiteDatabase::of($context)))->loadAll()),
            'templates' => (new FormTemplateRegistry())->all(),
        ]);
    }
}
