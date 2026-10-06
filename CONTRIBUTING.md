# Contributing

Thanks for helping improve **App Analytics & Remote Config**. This is a
plugin for NativePHP Mobile with a PHP layer (validation, defaults) and
hand-written Swift and Kotlin around the Firebase Analytics and Remote Config
SDKs. Contributions of all kinds are welcome — bug reports, docs, and code.

## Getting set up

```json
"repositories": [
    { "type": "path", "url": "../app-analytics" }
]
```

```bash
composer require vipertecpro/app-analytics:@dev
php artisan native:plugin:register vipertecpro/app-analytics
php artisan native:run ios       # or: android — recompiles the native code
```

## Running the tests

```bash
vendor/bin/pest
```

The PHP tests cover analytics validation, Remote Config typing, defaults and
sources; native behaviour is verified on a simulator / emulator, with and
without a Firebase config file.

## Project layout

```
src/Analytics.php                              analytics calls + validation
src/RemoteConfig.php                           flags: defaults, typed getters, fetch
src/Concerns/CallsNativeBridge.php             shared bridge call helper
src/Facades/Analytics.php, Facades/RemoteConfig.php
src/Events/RemoteConfigFetched.php             fetch succeeded (activated, keys)
src/Events/RemoteConfigFetchFailed.php         fetch failed (reason, message)
src/AppAnalyticsServiceProvider.php            config, singletons, cache refresh on fetch
config/app-analytics.php                       Remote Config defaults, fetch interval
resources/ios/AppAnalyticsFunctions.swift      init at launch + bridge functions
resources/android/AppAnalyticsFunctions.kt     init from assets + bridge functions
resources/js/appAnalytics.js                   JS bridge for legacy web-view apps
resources/boost/guidelines/core.blade.php      Laravel Boost / AI usage guidelines
nativephp.json                                 manifest: functions, dependencies, project files, events
```

## How it works

```
Build:  google-services.json      → app/src/main/assets/vipertecpro/   (optional)
        GoogleService-Info.plist  → NativePHP/                          (optional)

iOS launch:      initAppAnalytics() → FirebaseApp.configure(options:) only if the plist is bundled
Android first use: FirebaseApp.getApps() or initializeApp(options parsed from assets)

PHP  Analytics::logEvent($name, $params)   → validate → nativephp_call("AppAnalytics.LogEvent")
PHP  RemoteConfig::bool('flag')            → defaults ⟵ overridden by GetValues (source remote)
PHP  RemoteConfig::fetchAndActivate()      → RemoteConfigFetched / RemoteConfigFetchFailed → refresh()
```

## Verifying native changes

1. Without config files: the app starts, status says "not configured", calls
   are skipped, fetch reports `not_configured`, defaults are served.
2. With a test `google-services.json` (dummy ids) on Android: status says
   "configured", events are accepted, and the fetch fails with `error`.
3. With your real project's files: events appear in DebugView, and a value
   published in the console arrives after `fetchAndActivate(0)`.
