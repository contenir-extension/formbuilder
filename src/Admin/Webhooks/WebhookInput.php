<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Webhooks;

use Contenir\Extension\Formbuilder\Admin\Fields\Posted;
use Contenir\FormBuilder\Definition\WebhookDefinition;
use SensitiveParameter;

use function explode;
use function implode;
use function in_array;
use function preg_match;
use function str_contains;
use function strtoupper;
use function trim;

/**
 * A webhook's settings as its screen posts them, made into what the form
 * builder stores, as the built-in module read them: a name (Webhook when
 * left empty), its URL, POST or PUT, its signing secret if any, its extra
 * headers from "Name: value" lines, and whether it sends. Its place in the
 * list isn't posted, so an edit keeps it.
 */
final readonly class WebhookInput
{
    public const array METHODS = ['POST' => 'POST', 'PUT' => 'PUT'];

    /**
     * @mago-expect lint:excessive-parameter-list One property per setting; built by fromBody().
     */
    private function __construct(
        public string $name,
        public string $url,
        public string $method,
        #[SensitiveParameter]
        public ?string $secret,
        public string $headers,
        public bool $enabled,
    ) {}

    /**
     * @param array<array-key, mixed> $body
     */
    public static function fromBody(array $body): self
    {
        $posted = static fn(string $name): mixed => $body[$name] ?? null;
        $method = strtoupper(Posted::text($posted('method')));

        return new self(
            Posted::optional($posted('name')) ?? 'Webhook',
            Posted::text($posted('url')),
            in_array($method, self::METHODS, strict: true) ? $method : 'POST',
            Posted::optional($posted('secret')),
            Posted::text($posted('headers')),
            Posted::ticked($posted('enabled')),
        );
    }

    /**
     * A webhook's settings as its screen shows them: a new one's, sending,
     * or a saved one's.
     *
     * @return array<string, scalar|null>
     */
    public static function values(?WebhookDefinition $webhook): array
    {
        if (null === $webhook) {
            return ['name' => '', 'url' => '', 'method' => 'POST', 'secret' => '', 'headers' => '', 'enabled' => true];
        }

        $lines = [];
        foreach ($webhook->headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        return [
            'name'    => $webhook->name,
            'url'     => $webhook->url,
            'method'  => $webhook->method,
            'secret'  => $webhook->secret ?? '',
            'headers' => implode("\n", $lines),
            'enabled' => $webhook->enabled,
        ];
    }

    /**
     * "Name: value" lines as headers by name; blank lines, lines without a
     * colon and lines without a name are left out.
     *
     * @return array<string, string>
     */
    private static function headers(string $lines): array
    {
        $headers = [];
        foreach (explode("\n", $lines) as $line) {
            $parts = explode(':', $line, limit: 2);
            $name  = trim($parts[0]);
            if (! str_contains($line, ':') || '' === $name) {
                continue;
            }

            $headers[$name] = trim($parts[1] ?? '');
        }

        return $headers;
    }

    /**
     * What the form builder stores.
     *
     * @return array{name: string, url: string, method: string, secret: ?string, headers: array<string, string>, enabled: bool}
     */
    public function data(): array
    {
        return [
            'name'    => $this->name,
            'url'     => $this->url,
            'method'  => $this->method,
            'secret'  => $this->secret,
            'headers' => self::headers($this->headers),
            'enabled' => $this->enabled,
        ];
    }

    /**
     * What stops the webhook saving.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        if ('' === $this->url) {
            return ['A target URL is required.'];
        }

        return 1 === preg_match('~^https?://~i', $this->url) ? [] : ['URL must start with http:// or https://.'];
    }

    /**
     * The settings as the screen shows them again, as they were posted.
     *
     * @return array<string, scalar|null>
     */
    public function shown(): array
    {
        return [
            'name'    => $this->name,
            'url'     => $this->url,
            'method'  => $this->method,
            'secret'  => $this->secret ?? '',
            'headers' => $this->headers,
            'enabled' => $this->enabled,
        ];
    }
}
