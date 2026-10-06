<?php

namespace Vipertecpro\AppAnalytics;

use InvalidArgumentException;
use Vipertecpro\AppAnalytics\Concerns\CallsNativeBridge;

/**
 * Remote Config feature flags with in-app defaults.
 *
 * Defaults come from `config/app-analytics.php` (or setDefaults()).
 * Values fetched and activated on the device override them key by key. Until
 * the first successful fetch, without a Firebase config, or outside a native
 * app, the defaults are served — so a flag check never fails.
 */
class RemoteConfig
{
    use CallsNativeBridge;

    public const SOURCE_REMOTE = 'remote';

    public const SOURCE_DEFAULT = 'default';

    public const SOURCE_STATIC = 'static';

    /** @var array<string, string>|null Activated server values, cached per process. */
    protected ?array $remote = null;

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function __construct(protected array $defaults = [], protected int $minimumFetchInterval = 3600) {}

    /** @param  array<string, mixed>  $defaults  Merged over the configured defaults. */
    public function setDefaults(array $defaults): void
    {
        foreach (array_keys($defaults) as $key) {
            $this->assertKey((string) $key);
        }

        $this->defaults = array_replace($this->defaults, $defaults);
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * Fetch the latest values and activate them. Asynchronous: the result
     * arrives as {@see Events\RemoteConfigFetched} or
     * {@see Events\RemoteConfigFetchFailed}, and the cached values are
     * refreshed automatically when it does.
     *
     * @param  int|null  $minimumFetchInterval  Seconds; 0 while testing. Defaults to the config value.
     */
    public function fetchAndActivate(?int $minimumFetchInterval = null): void
    {
        $interval = $minimumFetchInterval ?? $this->minimumFetchInterval;

        if ($interval < 0) {
            throw new InvalidArgumentException('RemoteConfig: the minimum fetch interval cannot be negative.');
        }

        $this->call('AppAnalytics.FetchAndActivate', ['minimumFetchInterval' => $interval]);
    }

    /** Forget the cached server values so the next read asks the device. */
    public function refresh(): void
    {
        $this->remote = null;
    }

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->raw($key);

        return $value === null ? $default : (string) $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->raw($key);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return match (strtolower(trim((string) $value))) {
            '1', 'true', 't', 'yes', 'y', 'on' => true,
            '0', 'false', 'f', 'no', 'n', 'off', '' => false,
            default => $default,
        };
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->raw($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->raw($key);

        return is_numeric($value) ? (float) $value : $default;
    }

    /** A JSON value (object or array) decoded to an array, or the default. */
    public function json(string $key, array $default = []): array
    {
        $value = $this->raw($key);

        if (is_array($value)) {
            return $value;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : $default;
    }

    /** Where the value of a key comes from: `remote`, `default` or `static` (neither). */
    public function source(string $key): string
    {
        $this->assertKey($key);

        return match (true) {
            array_key_exists($key, $this->remoteValues()) => self::SOURCE_REMOTE,
            array_key_exists($key, $this->defaults) => self::SOURCE_DEFAULT,
            default => self::SOURCE_STATIC,
        };
    }

    /**
     * Every known key with its value and source.
     *
     * @return array<string, array{value: mixed, source: string}>
     */
    public function all(): array
    {
        $all = [];

        foreach (array_replace($this->defaults, $this->remoteValues()) as $key => $value) {
            $all[$key] = ['value' => $value, 'source' => $this->source((string) $key)];
        }

        ksort($all);

        return $all;
    }

    protected function raw(string $key): mixed
    {
        $this->assertKey($key);

        $remote = $this->remoteValues();

        return array_key_exists($key, $remote) ? $remote[$key] : ($this->defaults[$key] ?? null);
    }

    /** @return array<string, string> */
    protected function remoteValues(): array
    {
        if ($this->remote !== null) {
            return $this->remote;
        }

        $values = $this->call('AppAnalytics.GetValues')['values'] ?? [];
        $remote = [];

        foreach (is_array($values) ? $values : [] as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $remote[$key] = (string) $value;
            }
        }

        // Without a bridge the empty result is final; on the device an empty
        // answer is cached too, until refresh() or a fetch event.
        return $this->remote = $remote;
    }

    protected function assertKey(string $key): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,255}$/', $key) !== 1) {
            throw new InvalidArgumentException("RemoteConfig: \"{$key}\" is not a valid key — letters, digits and underscores, starting with a letter or underscore.");
        }
    }
}
