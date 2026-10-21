<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Unit\Uploads;

use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Formbuilder\Uploads\FileLinks;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

#[Group('unit')]
final class FileLinksTest extends TestCase
{
    private const string SIGNED_AT = '2026-10-20 09:00';

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function alteredProvider(): array
    {
        return [
            'another entry'         => [['entry' => '8']],
            'another field'         => [['field' => 'photo']],
            'a later expiry'        => [['expires' => '1900000000']],
            'another signature'     => [['signature' => 'forged']],
            'no entry'              => [['entry' => null]],
            'an entry not a number' => [['entry' => 'seven']],
            'no field'              => [['field' => null]],
            'no expiry'             => [['expires' => null]],
            'no signature'          => [['signature' => null]],
            'a field as a list'     => [['field' => ['cv']]],
        ];
    }

    #[Test]
    public function allowsALinkUntilItExpires(): void
    {
        static::assertSame(
            [true, true, false],
            [
                $this->links(self::SIGNED_AT)->allows($this->signed()),
                $this->links('2026-10-27 09:00')->allows($this->signed()),
                $this->links('2026-10-27 09:00:01')->allows($this->signed()),
            ],
        );
    }

    #[Test]
    public function linksToTheFileWebhookFromTheSitesAddress(): void
    {
        static::assertStringStartsWith(
            'https://www.example.com/api/extension/formbuilder/file?entry=7&field=cv&expires=1793091600&signature=',
            $this->links(self::SIGNED_AT)->link('https://www.example.com', 7, 'cv'),
        );
    }

    #[Test]
    #[DataProvider('alteredProvider')]
    public function refusesALinkChangedInAnyPart(array $change): void
    {
        $query = [...$this->signed(), ...$change];

        static::assertFalse($this->links(self::SIGNED_AT)->allows($query));
    }

    private function links(string $now): FileLinks
    {
        return new FileLinks('k3y-for-tests', new FrozenClock(new DateTimeImmutable($now)));
    }

    /**
     * The query of a link to entry 7's cv, signed at SIGNED_AT.
     *
     * @return array<array-key, mixed>
     */
    private function signed(): array
    {
        parse_str(
            (string) parse_url($this->links(self::SIGNED_AT)->link('', 7, 'cv'), PHP_URL_QUERY),
            $query,
        );

        return $query;
    }
}
