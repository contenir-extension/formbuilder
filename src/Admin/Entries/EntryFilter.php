<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\Extension\Formbuilder\Admin\Fields\Posted;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_key_exists;
use function is_string;
use function max;
use function trim;

/**
 * What the Entries tab is asked to show, from its address: a status (every
 * one when empty), a period back from now, a search by entry number or
 * address, and a page.
 */
final readonly class EntryFilter
{
    /**
     * @var array<string, array{string, int|null}> Label and seconds back, by period; null for all time
     */
    public const array PERIODS = [
        'all' => ['All time', null],
        '24h' => ['Last 24 hours', 86_400],
        '7d'  => ['Last 7 days', 604_800],
        '30d' => ['Last 30 days', 2_592_000],
    ];

    private function __construct(
        public string $status,
        public string $since,
        public string $search,
        public int $page,
    ) {}

    public static function of(ServerRequestInterface $request): self
    {
        $query = $request->getQueryParams();
        $since = self::text($query['since'] ?? null);

        return new self(
            self::text($query['status'] ?? null),
            array_key_exists($since, self::PERIODS) ? $since : 'all',
            trim(self::text($query['q'] ?? null)),
            max(1, Posted::number($query['pg'] ?? null)),
        );
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * The filter as an address's query, leaving out what isn't set, for the
     * page links to keep it.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter(
            ['q' => $this->search, 'status' => $this->status, 'since' => 'all' === $this->since ? '' : $this->since],
            static fn(string $value): bool => '' !== $value,
        );
    }

    /**
     * The earliest submission time the period lets in, by the clock; null
     * for all time.
     */
    public function sinceDate(ClockInterface $clock): ?string
    {
        $seconds = self::PERIODS[$this->since][1] ?? null;

        return null === $seconds ? null : $clock->now()->modify("-{$seconds} seconds")->format('Y-m-d H:i:s');
    }
}
