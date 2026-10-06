<?php

namespace Vipertecpro\AppAnalytics\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when RemoteConfig::fetchAndActivate() could not fetch.
 * The previously activated values (or the defaults) keep being served.
 *
 * @property string $reason `not_configured` (no Firebase config in the app), `throttled`
 *                          (asked again within the minimum interval), `network` or `error`.
 * @property ?string $message Platform detail.
 */
class RemoteConfigFetchFailed
{
    use Dispatchable, SerializesModels;

    public const NOT_CONFIGURED = 'not_configured';

    public const THROTTLED = 'throttled';

    public const NETWORK = 'network';

    public const ERROR = 'error';

    public function __construct(
        public string $reason = self::ERROR,
        public ?string $message = null,
    ) {}
}
