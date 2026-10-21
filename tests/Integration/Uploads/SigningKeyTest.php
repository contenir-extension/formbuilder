<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Tests\Integration\Uploads;

use Contenir\Extension\Formbuilder\Tests\TestAsset\Database\RivalKeyAdapter;
use Contenir\Extension\Formbuilder\Tests\Trait\FormsDatabaseTrait;
use Contenir\Extension\Formbuilder\Uploads\SigningKey;
use Override;
use PhpDb\Adapter\Exception\InvalidQueryException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function strlen;

/**
 * The key links to uploads are signed with: made once, kept, and read back
 * when another request made it first.
 */
#[Group('integration')]
final class SigningKeyTest extends TestCase
{
    use FormsDatabaseTrait;

    #[Test]
    public function failsWhenTheKeptKeyCannotBeRead(): void
    {
        $this->database->query(
            'CREATE TRIGGER lose AFTER INSERT ON ext_formbuilder_key BEGIN DELETE FROM ext_formbuilder_key; END',
            [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The key for links to uploads could not be read');

        (new SigningKey($this->database))->value();
    }

    #[Test]
    public function failsWhenTheKeyCanBeNeitherKeptNorRead(): void
    {
        $this->database->query(
            "CREATE TRIGGER refuse BEFORE INSERT ON ext_formbuilder_key BEGIN SELECT RAISE(ABORT, 'refused'); END",
            [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The key for links to uploads could not be kept');

        (new SigningKey($this->database))->value();
    }

    #[Test]
    public function keepsOneKeyOnly(): void
    {
        (new SigningKey($this->database))->value();

        $this->expectException(InvalidQueryException::class);

        $this->database->query('INSERT INTO ext_formbuilder_key (id, secret) VALUES (?, ?)', [1, 'another']);
    }

    #[Test]
    public function makesARandomKeyOnceAndKeepsIt(): void
    {
        $first  = (new SigningKey($this->database))->value();
        $second = (new SigningKey($this->database))->value();

        static::assertSame([64, $first], [strlen($first), $second]);
    }

    #[Test]
    public function readsTheKeyAnotherRequestKeptFirst(): void
    {
        static::assertSame('theirs', (new SigningKey(new RivalKeyAdapter($this->database, 'theirs')))->value());
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }
}
