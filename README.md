# App Analytics & Remote Config for NativePHP — works with Firebase

Measure how your NativePHP Mobile app is used and change its behaviour without
a release. This plugin gives you one PHP API for **analytics events, user
properties and screen views** and for **Remote Config feature flags with
in-app defaults**, backed by the Firebase Analytics and Remote Config SDKs —
the only third-party code — with hand-written Swift and Kotlin around them.

It is built to fail safe: an app **without** a `google-services.json` /
`GoogleService-Info.plist` still builds and runs. Analytics calls are skipped,
`Analytics::status()` tells you why, and every flag returns your default.

## Features

- 📊 **Events** — `Analytics::logEvent()` with up to 25 parameters, validated against the analytics limits before anything is sent
- 🧭 **Screen views** — `Analytics::logScreenView()` for SuperNative screens, which have no automatic screen tracking
- 🏷️ **User properties and user id** — set and clear them
- 🛡️ **Consent Mode v2** — `Analytics::setConsent()` for analytics storage, ad storage, ad user data and ad personalisation, plus a collection switch
- 🚩 **Feature flags** — `RemoteConfig::bool()`, `string()`, `int()`, `float()`, `json()` with your defaults, and where each value came from
- 🔁 **Fetch and activate** — asynchronous, with `RemoteConfigFetched` / `RemoteConfigFetchFailed` events and an automatic cache refresh
- 🧯 **Safe without a config file** — no crash, no build failure, defaults served
- 🔌 **No Gradle plugin** — Firebase is initialised from your config file at runtime, so the build never depends on it
- 📱 **iOS + Android** behind one PHP API

## Requirements

- PHP 8.4+
- NativePHP Mobile v4 (`nativephp/mobile: ^4.0`) — tested on iOS and Android against 4.6
- iOS 15+ / Android 10+ (API 29)
- A Firebase project for real data (free tier is enough)

## Installation

```bash
composer require vipertecpro/app-analytics
php artisan vendor:publish --tag=nativephp-plugins-provider   # once per app
php artisan native:plugin:register vipertecpro/app-analytics
php artisan vendor:publish --tag=app-analytics                # config: Remote Config defaults
php artisan native:run ios           # or: android — rebuild so the native code compiles in
```

> Requiring with Composer is **not** enough — an unregistered plugin does
> nothing. Always run `native:plugin:register` and confirm with
> `native:plugin:list`.

## Firebase setup

1. In the Firebase console, create a project (or open yours) and add an
   **Android app** with your `NATIVEPHP_APP_ID` as the package name, and an
   **iOS app** with the same value as the bundle id.
2. Download **`google-services.json`** (Android) and
   **`GoogleService-Info.plist`** (iOS) and put both in your **project root**,
   next to `composer.json`.
3. Rebuild. The plugin copies them into the native projects on every build:

| File in your project root | Copied to | Used by |
|---|---|---|
| `google-services.json` | `app/src/main/assets/vipertecpro/google-services.json` | read at runtime to initialise Firebase (no Google Services Gradle plugin needed) |
| `GoogleService-Info.plist` | `NativePHP/GoogleService-Info.plist` | read at launch through the plugin's init function |

4. Check it on the device: `Analytics::status()['configured']` is `true` and
   `projectId` is your project. Turn on DebugView in the Firebase console to
   see events as you log them.

Both files are optional. Keep them out of public repositories if you prefer;
they hold identifiers, not secrets, but there is no need to publish them.

## Permissions

| Platform | What the plugin adds | Why |
|---|---|---|
| Android | `INTERNET`, `ACCESS_NETWORK_STATE` | Sending analytics and fetching Remote Config |
| iOS | nothing | — |

The Analytics SDK collects an app instance id and device data. Declare it in
your App Store privacy details and Google Play data safety form, and ask for
consent where the law requires it — the free **Consent** plugin pairs with
`Analytics::setConsent()`.

## Usage

```php
use Vipertecpro\AppAnalytics\Facades\Analytics;
use Vipertecpro\AppAnalytics\Facades\RemoteConfig;

Analytics::logScreenView('Checkout');
Analytics::logEvent('purchase_started', ['plan' => 'pro', 'price' => 9.99, 'trial' => true]);
Analytics::setUserProperty('plan', 'pro');
Analytics::setUserId($user->id);          // never an email or phone number

if (RemoteConfig::bool('new_checkout')) {
    // the new flow
}

$title = RemoteConfig::string('welcome_title');
$limit = RemoteConfig::int('max_items', 20);
```

Fetch fresh flags, e.g. when the app starts:

```php
use Native\Mobile\Attributes\On;
use Vipertecpro\AppAnalytics\Events\RemoteConfigFetched;
use Vipertecpro\AppAnalytics\Events\RemoteConfigFetchFailed;

public function mount(): void
{
    RemoteConfig::fetchAndActivate();             // asynchronous
}

#[On(RemoteConfigFetched::class)]
public function onFlags(bool $activated): void
{
    // RemoteConfig already reads the new values; re-render whatever depends on them
}

#[On(RemoteConfigFetchFailed::class)]
public function onFlagsFailed(string $reason): void
{
    // not_configured | throttled | network | error — the old values and defaults keep working
}
```

Switch collection with your consent screen (here with the free Consent plugin):

```php
Event::listen(ConsentUpdated::class, function (ConsentUpdated $event) {
    Analytics::setConsent(
        analytics: $event->choices['analytics'] === true,
        ads: $event->choices['ads'] === true,
    );
});
```

### Defaults

`config/app-analytics.php`:

```php
'remote_config' => [
    'defaults' => [
        'new_checkout' => false,
        'welcome_title' => 'Welcome!',
        'max_items' => 20,
    ],
    'minimum_fetch_interval' => 3600,   // seconds; 0 while testing, 12 hours is a good production value
],
```

Defaults are served until the first successful fetch, when the app has no
Firebase config, and for any key the server does not define. Add more at
runtime with `RemoteConfig::setDefaults([...])`.

### API

```php
Analytics::logEvent(string $name, array $parameters = []): void;
Analytics::logScreenView(string $screenName, ?string $screenClass = null): void;
Analytics::setUserProperty(string $name, ?string $value): void;
Analytics::setUserId(?string $id): void;
Analytics::setCollectionEnabled(bool $enabled): void;
Analytics::setConsent(bool $analytics, bool $ads, ?bool $adPersonalization = null): void;
Analytics::resetData(): void;
Analytics::status(): array;      // configured, collectionEnabled, platform, appInstanceId, projectId, reason
Analytics::isConfigured(): bool;

RemoteConfig::fetchAndActivate(?int $minimumFetchInterval = null): void;
RemoteConfig::string(string $key, ?string $default = null): ?string;
RemoteConfig::bool(string $key, bool $default = false): bool;
RemoteConfig::int(string $key, int $default = 0): int;
RemoteConfig::float(string $key, float $default = 0.0): float;
RemoteConfig::json(string $key, array $default = []): array;
RemoteConfig::source(string $key): string;      // remote | default | static
RemoteConfig::all(): array;                      // key => [value, source]
RemoteConfig::setDefaults(array $defaults): void;
RemoteConfig::refresh(): void;
```

Validation throws `InvalidArgumentException` before anything is sent:
event, parameter and property names must be letters, digits and underscores
starting with a letter (40 characters, 24 for user properties), may not start
with `firebase_`, `google_` or `ga_`, and may not be one of the events the SDK
logs itself (`first_open`, `session_start`, …); string values are limited to
100 characters (36 for user properties).

### Events

| Event | Payload | Fired when |
|---|---|---|
| `Vipertecpro\AppAnalytics\Events\RemoteConfigFetched` | `bool $activated`, `int $keys` | The fetch succeeded. `activated` is false when the values were already current. |
| `Vipertecpro\AppAnalytics\Events\RemoteConfigFetchFailed` | `string $reason`, `?string $message` | `not_configured`, `throttled`, `network` or `error`. |

### Web-view screens

A JS bridge is shipped at `resources/js/appAnalytics.js` with `logEvent()`,
`logScreenView()`, `status()` and `fetchAndActivate()`.

## Limitations

- **No automatic screen tracking** for SuperNative screens — call
  `logScreenView()` in `mount()`.
- **Events take time to appear** in reports (up to a day); use DebugView
  while developing.
- **Without a config file on iOS** the Firebase SDK prints one log line at
  launch saying it is not configured. It is harmless.
- **One Firebase config.** If another plugin in your app already installs
  `GoogleService-Info.plist` through its own project-file declaration, the two
  will clash at build time; remove one of the declarations.
- **No A/B testing UI or real-time updates** in this release — Remote Config
  experiments still work, as they are served through the same values.

## Verified on

- iOS Simulator, iPhone 17 Pro (iOS 26.5), built with the Firebase 12 pods and
  **no** `GoogleService-Info.plist`: status "not configured", analytics calls
  skipped without errors, validation errors shown, fetch reporting
  `not_configured`, defaults served, light and dark mode.
- Android emulator, Pixel 9 (API 36): the same without a config file; then
  with a test `google-services.json` (dummy project): Firebase initialised from
  assets, status "configured", events accepted, and the fetch failing cleanly
  with `error` because the test key is not real.
- Not verified: delivery to a real Firebase project and real Remote Config
  values — that needs your project's config files.

## Demo

The companion demo app **free-plugins-demo** contains an "Analytics & Remote
Config" screen — status, events, user property, validation and the flags with
their sources — in one small `NativeComponent` you can copy from.

## Contributing

Issues and pull requests are welcome. See the `CONTRIBUTING.md` file included
with the package for local setup, the project layout and how it works.

## Changelog

See the `CHANGELOG.md` file included with the package for the full version history.

## License

MIT — see the `LICENSE` file included with the package.

vipertecpro is an independent developer. NativePHP, Laravel, Apple, Google, Firebase and other names are trademarks of their respective owners; this package is not affiliated with or endorsed by them. iOS and Apple are trademarks of Apple Inc. Android, Google Play and Firebase are trademarks of Google LLC.

App Analytics & Remote Config is a free plugin from vipertecpro.com, home of the paid plugins for NativePHP Mobile: Rich-Text Editor, Onboarding & Tours, Health Data and Native Charts.
