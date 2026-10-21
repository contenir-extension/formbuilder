<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Uploads;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Exception\InvalidQueryException;
use PhpDb\ResultSet\ResultSetInterface;
use Random\RandomException;
use RuntimeException;

use function bin2hex;
use function is_string;
use function random_bytes;

/**
 * The key the add-on signs its links to uploads with: made at random the
 * first time one is signed and kept in ext_formbuilder_key. Deleting its row
 * makes a new one the next time, and every link signed with the old one
 * stops working. When two requests make one at once, the second's insert
 * fails and it reads the first's.
 */
final readonly class SigningKey
{
    public function __construct(
        private AdapterInterface $database,
    ) {}

    /**
     * @throws RandomException  When no random key can be made.
     * @throws RuntimeException When the key can be neither read nor kept.
     */
    public function value(): string
    {
        $stored = $this->stored();
        if (null !== $stored) {
            return $stored;
        }

        try {
            $this->database->query('INSERT INTO ext_formbuilder_key (id, secret) VALUES (?, ?)', [
                1,
                bin2hex(random_bytes(32)),
            ]);
        } catch (InvalidQueryException $failure) {
            return $this->stored() ?? throw new RuntimeException(
                'The key for links to uploads could not be kept',
                previous: $failure,
            );
        }

        return $this->stored() ?? throw new RuntimeException('The key for links to uploads could not be read');
    }

    /**
     * @mago-expect analysis:mixed-assignment A column's value is untyped until it is checked below.
     */
    private function stored(): ?string
    {
        $result = $this->database->query('SELECT secret FROM ext_formbuilder_key WHERE id = ?', [1]);
        $rows   = $result instanceof ResultSetInterface ? $result->toArray() : [];
        $secret = $rows[0]['secret'] ?? null;

        return is_string($secret) && '' !== $secret ? $secret : null;
    }
}
