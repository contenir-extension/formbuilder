<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\TestAsset\Operation;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Operation\OperationContextInterface;
use Contenir\Extension\Api\Operation\SectionViewInterface;
use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Api\Testing\ServiceMap;
use LogicException;
use Override;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * An operation's context as the CMS gives it when someone presses the
 * operation's button: the screen's query and posted values, and the host's
 * services.
 */
final readonly class PressedOperation implements OperationContextInterface
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, object> $services
     */
    public function __construct(
        private array $query,
        private array $services = [],
    ) {}

    #[Override]
    public function allPost(): array
    {
        return [];
    }

    /**
     * A described screen's operation, not a settings section's: no add-on's
     * settings come with it.
     */
    #[Override]
    public function extension(): ?ExtensionContextInterface
    {
        return null;
    }

    #[Override]
    public function isPost(): bool
    {
        return true;
    }

    #[Override]
    public function logger(): LoggerInterface
    {
        return new MemoryLogger();
    }

    #[Override]
    public function param(string $name, mixed $default = null): mixed
    {
        return $this->query[$name] ?? $default;
    }

    #[Override]
    public function post(string $name, mixed $default = null): mixed
    {
        return $default;
    }

    #[Override]
    public function section(): SectionViewInterface
    {
        throw new LogicException('A screen operation has no section');
    }

    #[Override]
    public function services(): ContainerInterface
    {
        return new ServiceMap($this->services);
    }

    #[Override]
    public function sitePath(): string
    {
        return '/site';
    }
}
