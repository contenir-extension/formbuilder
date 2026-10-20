<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin;

use Contenir\Extension\Formbuilder\FormbuilderExtension;

use function http_build_query;

/**
 * Where the add-on's handlers are, for redirects: the API mounts each at
 * /admin/extension/<handle>/<name>.
 */
final class Urls
{
    /**
     * @param array<string, scalar> $query
     */
    public static function handler(string $name, array $query = []): string
    {
        $url = '/admin/extension/' . FormbuilderExtension::HANDLE . "/{$name}";

        return [] === $query ? $url : "{$url}?" . http_build_query($query);
    }
}
