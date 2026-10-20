<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Api\Handler\HandlerResult;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_numeric;

/**
 * Which form a request is about: ?form=<id> in its address, or a posted
 * form field. Zero when it names none.
 */
final class FormId
{
    /**
     * Back to the Forms home, saying the form is gone.
     */
    public static function missing(): HandlerResult
    {
        return HandlerResult::redirect(Urls::handler(FormsHandler::NAME))->withMessage(
            'warning',
            'That form no longer exists.',
        );
    }

    public static function of(ServerRequestInterface $request): int
    {
        $posted = $request->getParsedBody();

        return (
            self::number($request->getQueryParams()['form'] ?? null)
                ?? self::number(is_array($posted) ? $posted['form'] ?? null : null)
                ?? 0
        );
    }

    private static function number(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
