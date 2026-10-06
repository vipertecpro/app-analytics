<?php

use Vipertecpro\AppAnalytics\Analytics;

/**
 * Validation happens in PHP before the bridge; nativephp_call() is absent
 * here, so valid calls are silent no-ops.
 */
it('accepts valid events, properties and ids', function () {
    $analytics = new Analytics;

    $analytics->logEvent('purchase_started', ['plan' => 'pro', 'price' => 9.99, 'trial' => true, 'items' => 3]);
    $analytics->logScreenView('Checkout');
    $analytics->setUserProperty('favourite_plugin', 'photo_kit');
    $analytics->setUserProperty('favourite_plugin', null);
    $analytics->setUserId('user_42');
    $analytics->setUserId(null);
    $analytics->setCollectionEnabled(false);
    $analytics->setConsent(analytics: true, ads: false);
    $analytics->resetData();

    expect(true)->toBeTrue();
});

it('rejects invalid event names', function (string $name, string $message) {
    expect(fn () => (new Analytics)->logEvent($name))->toThrow(InvalidArgumentException::class, $message);
})->with([
    ['', 'not a valid event name'],
    ['1st_event', 'not a valid event name'],
    ['has-dash', 'not a valid event name'],
    [str_repeat('a', 41), 'not a valid event name'],
    ['firebase_custom', 'may not start with "firebase_"'],
    ['GA_thing', 'may not start with "ga_"'],
    ['first_open', 'logged automatically'],
]);

it('rejects bad parameters', function (array $parameters, string $message) {
    expect(fn () => (new Analytics)->logEvent('ok', $parameters))->toThrow(InvalidArgumentException::class, $message);
})->with([
    [array_fill_keys(array_map(fn ($i) => "p{$i}", range(1, 26)), 1), 'at most 25 parameters'],
    [['note' => str_repeat('x', 101)], 'longer than 100 characters'],
    [['list' => [1, 2]], 'must be a string, number or boolean'],
    [['empty' => null], 'is null'],
    [['google_id' => 'x'], 'may not start with "google_"'],
]);

it('rejects bad user properties and ids', function () {
    expect(fn () => (new Analytics)->setUserProperty(str_repeat('a', 25), 'x'))->toThrow(InvalidArgumentException::class, 'user property name')
        ->and(fn () => (new Analytics)->setUserProperty('plan', str_repeat('x', 37)))->toThrow(InvalidArgumentException::class, 'longer than 36')
        ->and(fn () => (new Analytics)->setUserId(''))->toThrow(InvalidArgumentException::class, '1–256');
});

it('reports why it is not configured outside the app', function () {
    expect((new Analytics)->status())->toMatchArray(['configured' => false, 'reason' => 'no_bridge'])
        ->and((new Analytics)->isConfigured())->toBeFalse();
});
