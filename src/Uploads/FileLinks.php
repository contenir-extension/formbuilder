<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Uploads;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Formbuilder\Admin\Urls;
use Contenir\Extension\Formbuilder\Persistence\SiteDatabase;
use LogicException;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Random\RandomException;
use RuntimeException;

use function hash_equals;
use function hash_hmac;
use function is_numeric;
use function is_string;

/**
 * Signed links to the file an entry's upload field holds, for notification
 * emails. The public file webhook sends the file to anyone holding one until
 * it expires, seven days after it was signed; changing any part of it breaks
 * its signature.
 */
final readonly class FileLinks
{
    public const int LIFETIME = 604_800;

    public function __construct(
        private string $key,
        private ClockInterface $clock,
    ) {}

    /**
     * Links signed with the add-on's key, on the host's clock.
     *
     * @throws ContainerExceptionInterface When the host can't give the site's database.
     * @throws LogicException              When the host offers no site database.
     * @throws RandomException             When no key can be made.
     * @throws RuntimeException            When the key can be neither read nor kept.
     */
    public static function of(ExtensionContextInterface $context): self
    {
        return new self((new SigningKey(SiteDatabase::of($context)))->value(), $context->clock());
    }

    /**
     * Whether a request's query is a link signed with the key that hasn't
     * expired.
     *
     * @param array<array-key, mixed> $query
     *
     * @mago-expect analysis:mixed-assignment A query's values are untyped until they are checked below.
     */
    public function allows(array $query): bool
    {
        $entry     = $query['entry'] ?? null;
        $field     = $query['field'] ?? null;
        $expires   = $query['expires'] ?? null;
        $signature = $query['signature'] ?? null;
        if (! is_numeric($entry) || ! is_string($field) || ! is_numeric($expires) || ! is_string($signature)) {
            return false;
        }

        $until = (int) $expires;

        return (
            $until >= $this->clock->now()->getTimestamp()
                && hash_equals($this->signature((int) $entry, $field, $until), $signature)
        );
    }

    /**
     * A link to the file, from $baseUrl, the site's address.
     */
    public function link(string $baseUrl, int $entryId, string $field): string
    {
        $expires = $this->clock->now()->getTimestamp() + self::LIFETIME;

        return $baseUrl
            . Urls::webhook(FileWebhook::NAME, [
                'entry'     => $entryId,
                'field'     => $field,
                'expires'   => $expires,
                'signature' => $this->signature($entryId, $field, $expires),
            ]);
    }

    private function signature(int $entryId, string $field, int $expires): string
    {
        return hash_hmac('sha256', "{$entryId}\n{$field}\n{$expires}", $this->key);
    }
}
