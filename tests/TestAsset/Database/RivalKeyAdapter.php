<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\TestAsset\Database;

use Override;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Driver;
use PhpDb\Adapter\ParameterContainer;
use PhpDb\Adapter\Platform;
use PhpDb\Adapter\Profiler;
use PhpDb\ResultSet;

use function str_starts_with;

/**
 * The site's database as a request sees it when another keeps the signing
 * key between its looking for one and keeping its own: the rival's key is
 * kept just before the first insert into ext_formbuilder_key runs.
 */
final class RivalKeyAdapter implements AdapterInterface
{
    private bool $raced = false;

    public function __construct(
        private readonly AdapterInterface $database,
        private readonly string $rival,
    ) {}

    #[Override]
    public function createStatement(
        ?string $initialSql = null,
        ParameterContainer|array $initialParameters = [],
    ): Driver\StatementInterface {
        return $this->database->createStatement($initialSql, $initialParameters);
    }

    #[Override]
    public function executeQuery(Driver\StatementInterface|string $sql): Driver\ResultInterface
    {
        return $this->database->executeQuery($sql);
    }

    #[Override]
    public function getDriver(): Driver\DriverInterface
    {
        return $this->database->getDriver();
    }

    /**
     * @return array<array-key, mixed>
     */
    #[Override]
    public function getHelpers(): array
    {
        return (array) $this->database->getHelpers();
    }

    #[Override]
    public function getPlatform(): Platform\PlatformInterface
    {
        return $this->database->getPlatform();
    }

    #[Override]
    public function getProfiler(): ?Profiler\ProfilerInterface
    {
        return $this->database->getProfiler();
    }

    #[Override]
    public function getQueryResultSetPrototype(): ResultSet\ResultSetInterface
    {
        return $this->database->getQueryResultSetPrototype();
    }

    #[Override]
    public function prepareQuery(string $sql, ParameterContainer|array $parameters = []): Driver\StatementInterface
    {
        return $this->database->prepareQuery($sql, $parameters);
    }

    #[Override]
    public function query(
        string $sql,
        ParameterContainer|array|string $parametersOrQueryMode = self::QUERY_MODE_PREPARE,
        ?ResultSet\ResultSetInterface $resultPrototype = null,
    ): Driver\StatementInterface|ResultSet\ResultSetInterface|Driver\ResultInterface {
        if (! $this->raced && str_starts_with($sql, 'INSERT INTO ext_formbuilder_key')) {
            $this->raced = true;
            $this->database->query('INSERT INTO ext_formbuilder_key (id, secret) VALUES (?, ?)', [1, $this->rival]);
        }

        return $this->database->query($sql, $parametersOrQueryMode, $resultPrototype);
    }
}
