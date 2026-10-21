<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Preview;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

use function array_keys;
use function is_string;

/**
 * A test submission's files as the form builder reads them, by field name,
 * in the shape PHP gives uploads: name, type, temporary path, error and
 * size. The preview stores none of them.
 */
final class UploadedFiles
{
    /**
     * @return array<string, array{name: ?string, type: ?string, tmp_name: string, error: int, size: ?int}>
     *
     * @throws RuntimeException When a file's stream can't be read.
     */
    public static function of(ServerRequestInterface $request): array
    {
        $uploaded = $request->getUploadedFiles();
        $files    = [];
        foreach (array_keys($uploaded) as $name) {
            $described = self::described($uploaded[$name] ?? null);
            if (null === $described) {
                continue;
            }

            $files[(string) $name] = $described;
        }

        return $files;
    }

    /**
     * The file in PHP's upload shape; null when it isn't an uploaded file.
     *
     * @return array{name: ?string, type: ?string, tmp_name: string, error: int, size: ?int}|null
     *
     * @throws RuntimeException When its stream can't be read.
     */
    private static function described(mixed $file): ?array
    {
        if (! $file instanceof UploadedFileInterface) {
            return null;
        }

        return [
            'name'     => $file->getClientFilename(),
            'type'     => $file->getClientMediaType(),
            'tmp_name' => self::path($file->getStream()->getMetadata('uri')),
            'error'    => $file->getError(),
            'size'     => $file->getSize(),
        ];
    }

    private static function path(mixed $uri): string
    {
        return is_string($uri) ? $uri : '';
    }
}
