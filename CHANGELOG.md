# Changelog

All notable changes to `vipertecpro/app-analytics` are documented here.
The format is based on Keep a Changelog, and this project adheres to
Semantic Versioning.

## [1.0.0] - 2026-10-07

First release. Verified on the iOS Simulator (iPhone 17 Pro, iOS 26.5) and an
Android emulator (Pixel 9, API 36) against NativePHP Mobile 4.6, without a
Firebase config and (Android) with a test config; delivery to a real Firebase
project was not exercised.

### Added
- **Analytics** — `logEvent()`, `logScreenView()`, `setUserProperty()`,
  `setUserId()`, `setCollectionEnabled()`, Consent Mode v2 `setConsent()`,
  `resetData()` and `status()`, with input validated against the analytics
  limits before anything is sent.
- **Remote Config** — typed getters with in-app defaults from
  `config/app-analytics.php`, `source()`, `all()`, `setDefaults()`, and an
  asynchronous `fetchAndActivate()` with `RemoteConfigFetched` /
  `RemoteConfigFetchFailed` events and an automatic cache refresh.
- **Fail-safe setup** — the config files are optional project files; Android
  initialises Firebase from assets at runtime without the Google Services
  Gradle plugin, iOS configures it at launch only when the plist is bundled.
- A JS bridge for legacy web-view apps and Laravel Boost guidelines.

### Notes
- Dependencies: Firebase Analytics 22.1.2 and Remote Config 22.0.1 on
  Android (the newest that compile with the Kotlin 2.0 toolchain of NativePHP
  Mobile 4.6), the FirebaseAnalytics and FirebaseRemoteConfig 12.x pods on iOS.
