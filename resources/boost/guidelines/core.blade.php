## vipertecpro/app-analytics

App Analytics & Remote Config for NativePHP Mobile, working with Firebase:
analytics events, user properties, screen views, Consent Mode signals, and
Remote Config feature flags with in-app defaults. Works with NativePHP Mobile v4.

### What it does / does not do

- Without `google-services.json` / `GoogleService-Info.plist` in the project
  root the app still builds and runs: analytics calls are skipped and every
  flag returns its default. `Analytics::status()['reason']` says why.
- Validation runs in PHP first and throws `InvalidArgumentException` for bad
  names, reserved prefixes (`firebase_`, `google_`, `ga_`), automatic event
  names, more than 25 parameters or over-long values.
- SuperNative screens are not tracked automatically: call `logScreenView()`.

### Facade methods

`use Vipertecpro\AppAnalytics\Facades\Analytics;`

- `Analytics::logEvent(string $name, array $parameters = [])`, `logScreenView($screen, ?$class)`
- `Analytics::setUserProperty($name, ?$value)`, `setUserId(?$id)`, `setCollectionEnabled(bool)`
- `Analytics::setConsent(bool $analytics, bool $ads, ?bool $adPersonalization = null)`, `resetData()`
- `Analytics::status(): array`, `isConfigured(): bool`

`use Vipertecpro\AppAnalytics\Facades\RemoteConfig;`

- `RemoteConfig::bool|string|int|float|json($key, $default)` — remote value, else config default, else `$default`
- `RemoteConfig::fetchAndActivate(?int $minimumFetchInterval = null)` — async
- `RemoteConfig::source($key)` (`remote`, `default`, `static`), `all()`, `setDefaults([...])`, `refresh()`

### Events

- `Vipertecpro\AppAnalytics\Events\RemoteConfigFetched(bool $activated, int $keys)`
- `Vipertecpro\AppAnalytics\Events\RemoteConfigFetchFailed(string $reason, ?string $message)` — `not_configured`, `throttled`, `network`, `error`

### Do

- Put defaults for every flag in `config/app-analytics.php`.
- Log `logScreenView()` in each screen's `mount()`.
- Tie `setConsent()` to the user's consent choices.

### Don't

- Don't send personal data (emails, phone numbers) as user ids or parameters.
- Don't call `fetchAndActivate(0)` in production; respect the fetch interval.
