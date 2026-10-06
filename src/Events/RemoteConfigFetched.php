<?php

namespace Vipertecpro\AppAnalytics\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after RemoteConfig::fetchAndActivate() succeeded.
 *
 * @property bool $activated True when new values were activated; false when the fetched values were already active.
 * @property int $keys How many server keys are active now.
 */
class RemoteConfigFetched
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public bool $activated = false,
        public int $keys = 0,
    ) {}
}
