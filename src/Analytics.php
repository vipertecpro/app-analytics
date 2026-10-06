<?php

namespace Vipertecpro\AppAnalytics;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Vipertecpro\AppAnalytics\Concerns\CallsNativeBridge;

/**
 * Analytics events, user properties and screen views.
 *
 * Every call validates its input against the analytics limits first, so a
 * bad event name fails loudly in development instead of being dropped
 * silently on the device. When the app has no Firebase config (or outside a
 * native app) calls are skipped and {@see status()} says why.
 */
class Analytics
{
    use CallsNativeBridge;

    /** Prefixes the analytics backend reserves for its own events and properties. */
    public const RESERVED_PREFIXES = ['firebase_', 'google_', 'ga_'];

    /** Event names logged automatically, which apps may not log themselves. */
    public const RESERVED_EVENTS = [
        'ad_activeview', 'ad_click', 'ad_exposure', 'ad_query', 'ad_reward', 'adunit_exposure', 'app_background',
        'app_clear_data', 'app_exception', 'app_remove', 'app_store_refund', 'app_store_subscription_cancel',
        'app_store_subscription_convert', 'app_store_subscription_renew', 'app_update', 'app_upgrade',
        'dynamic_link_app_open', 'dynamic_link_app_update', 'dynamic_link_first_open', 'error', 'first_open',
        'first_visit', 'in_app_purchase', 'notification_dismiss', 'notification_foreground', 'notification_open',
        'notification_receive', 'os_update', 'session_start', 'session_start_with_rollout', 'user_engagement',
    ];

    public function __construct(protected ?LoggerInterface $logger = null, protected bool $logCalls = false) {}

    /**
     * Log an event. Names: 1–40 letters, digits and underscores, starting
     * with a letter. Up to 25 parameters; parameter values are strings (up to
     * 100 characters), numbers or booleans.
     *
     * @param  array<string, string|int|float|bool|null>  $parameters
     */
    public function logEvent(string $name, array $parameters = []): void
    {
        $this->assertName($name, 'event', 40);

        if (in_array($name, self::RESERVED_EVENTS, true)) {
            throw new InvalidArgumentException("Analytics: \"{$name}\" is logged automatically and cannot be logged by the app.");
        }

        $this->send('AppAnalytics.LogEvent', ['name' => $name, 'parameters' => $this->parameters($parameters)]);
    }

    /** Log a screen view (the `screen_view` event). */
    public function logScreenView(string $screenName, ?string $screenClass = null): void
    {
        $this->send('AppAnalytics.LogEvent', [
            'name' => 'screen_view',
            'parameters' => $this->parameters(array_filter([
                'screen_name' => $screenName,
                'screen_class' => $screenClass ?? $screenName,
            ], fn ($value) => $value !== null)),
        ]);
    }

    /** Set a user property (name up to 24 characters, value up to 36), or clear it with null. */
    public function setUserProperty(string $name, ?string $value): void
    {
        $this->assertName($name, 'user property', 24);

        if ($value !== null && mb_strlen($value) > 36) {
            throw new InvalidArgumentException("Analytics: the value of user property \"{$name}\" is longer than 36 characters.");
        }

        $this->send('AppAnalytics.SetUserProperty', ['name' => $name, 'value' => $value]);
    }

    /** Set the user id (up to 256 characters), or clear it with null. Never use an email or phone number. */
    public function setUserId(?string $id): void
    {
        if ($id !== null && ($id === '' || mb_strlen($id) > 256)) {
            throw new InvalidArgumentException('Analytics: the user id must be 1–256 characters.');
        }

        $this->send('AppAnalytics.SetUserId', ['id' => $id]);
    }

    /** Turn collection on or off. Off is remembered across launches. */
    public function setCollectionEnabled(bool $enabled): void
    {
        $this->send('AppAnalytics.SetCollectionEnabled', ['enabled' => $enabled]);
    }

    /**
     * Consent Mode v2 signals. `analytics` drives analytics storage; `ads`
     * drives ad storage, ad user data and ad personalisation together, unless
     * `adPersonalization` is given separately.
     */
    public function setConsent(bool $analytics, bool $ads, ?bool $adPersonalization = null): void
    {
        $this->send('AppAnalytics.SetConsent', [
            'analyticsStorage' => $analytics,
            'adStorage' => $ads,
            'adUserData' => $ads,
            'adPersonalization' => $adPersonalization ?? $ads,
        ]);
    }

    /** Clear all analytics data on this device and start a new app instance id. */
    public function resetData(): void
    {
        $this->send('AppAnalytics.ResetData');
    }

    /**
     * @return array{configured: bool, collectionEnabled: bool, platform: string, appInstanceId: string|null, projectId: string|null, reason: string|null}
     *                                                                                                                                                     `reason` explains an unconfigured state: `no_bridge`, `no_config_file` or `invalid_config`.
     */
    public function status(): array
    {
        if (! $this->hasBridge()) {
            return ['configured' => false, 'collectionEnabled' => false, 'platform' => 'none', 'appInstanceId' => null, 'projectId' => null, 'reason' => 'no_bridge'];
        }

        $result = $this->call('AppAnalytics.Status');

        return [
            'configured' => (bool) ($result['configured'] ?? false),
            'collectionEnabled' => (bool) ($result['collectionEnabled'] ?? false),
            'platform' => (string) ($result['platform'] ?? 'unknown'),
            'appInstanceId' => is_string($result['appInstanceId'] ?? null) ? $result['appInstanceId'] : null,
            'projectId' => is_string($result['projectId'] ?? null) ? $result['projectId'] : null,
            'reason' => is_string($result['reason'] ?? null) ? $result['reason'] : null,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->status()['configured'];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, string|int|float>
     */
    protected function parameters(array $parameters): array
    {
        if (count($parameters) > 25) {
            throw new InvalidArgumentException('Analytics: an event can have at most 25 parameters.');
        }

        $clean = [];

        foreach ($parameters as $key => $value) {
            $this->assertName((string) $key, 'parameter', 40);

            $clean[$key] = match (true) {
                $value === null => throw new InvalidArgumentException("Analytics: parameter \"{$key}\" is null."),
                is_bool($value) => $value ? 1 : 0,
                is_int($value), is_float($value) => $value,
                is_string($value) && mb_strlen($value) <= 100 => $value,
                is_string($value) => throw new InvalidArgumentException("Analytics: parameter \"{$key}\" is longer than 100 characters."),
                default => throw new InvalidArgumentException("Analytics: parameter \"{$key}\" must be a string, number or boolean."),
            };
        }

        return $clean;
    }

    protected function assertName(string $name, string $what, int $max): void
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,'.($max - 1).'}$/', $name) !== 1) {
            throw new InvalidArgumentException("Analytics: \"{$name}\" is not a valid {$what} name — 1–{$max} letters, digits or underscores, starting with a letter.");
        }

        foreach (self::RESERVED_PREFIXES as $prefix) {
            if (str_starts_with(strtolower($name), $prefix)) {
                throw new InvalidArgumentException("Analytics: {$what} names may not start with \"{$prefix}\".");
            }
        }
    }

    protected function send(string $method, array $params = []): void
    {
        if ($this->logCalls) {
            $this->logger?->debug("[analytics] {$method}", $params);
        }

        $this->call($method, $params);
    }
}
