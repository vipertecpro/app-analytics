<?php

namespace Vipertecpro\AppAnalytics\Concerns;

trait CallsNativeBridge
{
    /** Whether the native bridge is present (false in tests and on the web). */
    protected function hasBridge(): bool
    {
        return function_exists('nativephp_call');
    }

    /**
     * Call a bridge function and decode its JSON result. A missing bridge or
     * an error response yields an empty array.
     *
     * @return array<string, mixed>
     */
    protected function call(string $method, array $params = []): array
    {
        if (! $this->hasBridge()) {
            return [];
        }

        $raw = nativephp_call($method, json_encode((object) $params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return is_array($decoded) && ($decoded['status'] ?? null) !== 'error' ? $decoded : [];
    }
}
