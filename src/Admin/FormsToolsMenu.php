<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Module\MenuGroup;
use Contenir\Extension\Api\Module\MenuInterface;
use Contenir\Extension\Api\Module\MenuItem;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function str_ends_with;

/**
 * The Forms area's Tools in its section menu, as Simon chose on 21 Oct: the
 * occasional actions that change every form, each opening a page that says
 * what it does before it runs. The host shows each only to someone who may
 * use it.
 */
final class FormsToolsMenu implements MenuInterface
{
    /**
     * @var array<string, string> Labels, by handler
     */
    private const array TOOLS = [
        EntriesRedactHandler::NAME => 'Redact expired entries',
        UploadsMoveHandler::NAME   => 'Move uploads to private storage',
    ];

    #[Override]
    public function groups(ServerRequestInterface $request, ExtensionContextInterface $context): array
    {
        $items = [];
        foreach (self::TOOLS as $handler => $label) {
            $items[] = new MenuItem(
                $label,
                $handler,
                current: str_ends_with($request->getUri()->getPath(), "/{$handler}"),
            );
        }

        return [new MenuGroup('Tools', $items)];
    }
}
