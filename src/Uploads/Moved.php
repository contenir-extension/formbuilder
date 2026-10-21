<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Uploads;

/**
 * Where a file is kept in private storage once moved there, and whether it
 * came from public storage just now rather than being there already.
 */
final readonly class Moved
{
    public function __construct(
        public string $path,
        public bool $fromPublic,
    ) {}
}
