<?php

/**
 * Plugin structure tests for App Analytics & Remote Config — manifest, native files, PHP classes.
 *
 * Run with: ./vendor/bin/pest
 */
beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifest = json_decode(file_get_contents($this->pluginPath.'/nativephp.json'), true);
    $this->swift = file_get_contents($this->pluginPath.'/resources/ios/AppAnalyticsFunctions.swift');
    $this->kotlin = file_get_contents($this->pluginPath.'/resources/android/AppAnalyticsFunctions.kt');
});

describe('Plugin Manifest', function () {
    it('has required fields', function () {
        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($this->manifest['name'])->toBe('vipertecpro/app-analytics');
        expect($this->manifest['namespace'])->toBe('AppAnalytics');
        expect($this->manifest['version'])->toBe('1.0.0');
    });

    it('exposes the bridge functions on both platforms', function () {
        expect(array_column($this->manifest['bridge_functions'], 'name'))->toBe([
            'AppAnalytics.Status', 'AppAnalytics.LogEvent', 'AppAnalytics.SetUserProperty', 'AppAnalytics.SetUserId',
            'AppAnalytics.SetCollectionEnabled', 'AppAnalytics.SetConsent', 'AppAnalytics.ResetData',
            'AppAnalytics.FetchAndActivate', 'AppAnalytics.GetValues',
        ]);

        foreach ($this->manifest['bridge_functions'] as $function) {
            $android = explode('.', $function['android']);
            $ios = explode('.', $function['ios']);
            expect($this->kotlin)->toContain('class '.end($android).'(');
            expect($this->swift)->toContain('class '.end($ios).':');
        }
    });

    it('depends only on the Firebase SDKs and never on the Google Services Gradle plugin', function () {
        expect($this->manifest['android']['dependencies']['implementation'])->toBe([
            'com.google.firebase:firebase-analytics:22.1.2',
            'com.google.firebase:firebase-config:22.0.1',
        ]);
        expect($this->manifest['android'])->not->toHaveKey('gradle_plugins');
        expect(array_column($this->manifest['ios']['dependencies']['pods'], 'name'))->toBe(['FirebaseAnalytics', 'FirebaseRemoteConfig']);
    });

    it('copies the Firebase config files only when the app has them', function () {
        expect($this->manifest['android']['project_files'])->toBe([[
            'sources' => ['google-services.json'],
            'destination' => 'app/src/main/assets/vipertecpro/google-services.json',
            'required' => false,
        ]]);
        expect($this->manifest['ios']['project_files'][0])->toMatchArray(['sources' => ['GoogleService-Info.plist'], 'required' => false]);
        expect($this->manifest['ios']['init_function'])->toBe('initAppAnalytics');
        expect($this->swift)->toContain('func initAppAnalytics()');
    });

    it('declares the Remote Config events and sends them from both platforms', function () {
        expect($this->manifest['events'])->toBe([
            'Vipertecpro\\AppAnalytics\\Events\\RemoteConfigFetched',
            'Vipertecpro\\AppAnalytics\\Events\\RemoteConfigFetchFailed',
        ]);

        foreach ($this->manifest['events'] as $event) {
            expect(class_exists($event))->toBeTrue();
            $escaped = str_replace('\\', '\\\\', $event);
            expect($this->swift)->toContain($escaped);
            expect($this->kotlin)->toContain($escaped);
        }
    });

    it('has marketplace metadata filled in', function () {
        expect($this->manifest['category'])->toBe('analytics');
        expect($this->manifest['pricing']['type'])->toBe('free');
        expect(array_slice(getimagesize($this->pluginPath.'/resources/icon.png'), 0, 2))->toBe([512, 512]);
    });
});

describe('Native Code', function () {
    it('skips every call when Firebase is not configured', function () {
        expect(substr_count($this->swift, 'guard FirebaseSetup.configured'))->toBeGreaterThanOrEqual(5);
        expect($this->swift)->toContain('Bundle.main.path(forResource: "GoogleService-Info", ofType: "plist")');
        expect($this->kotlin)->toContain('"vipertecpro/google-services.json"')->toContain('FirebaseApp.getApps(context)');
        expect($this->kotlin)->toContain('"not_configured"');
        expect($this->swift)->toContain('"not_configured"');
    });
});

describe('Documentation', function () {
    it('ships the product files and the config', function () {
        foreach (['README.md', 'CHANGELOG.md', 'CONTRIBUTING.md', 'RELEASING.md', 'LICENSE', 'config/app-analytics.php', 'resources/boost/guidelines/core.blade.php', 'resources/js/appAnalytics.js'] as $file) {
            expect(file_exists($this->pluginPath.'/'.$file))->toBeTrue("missing {$file}");
        }
    });

    it('keeps the README free of links and ends with the store line', function () {
        $readme = file_get_contents($this->pluginPath.'/README.md');

        expect($readme)->not->toMatch('/\]\(/')->not->toMatch('/https?:\/\//')->not->toContain('<a ');
        expect(trim(last(explode("\n", trim($readme)))))->toContain('vipertecpro.com');
    });

    it('never uses a third-party mark as its own name', function () {
        $readme = file_get_contents($this->pluginPath.'/README.md');
        $title = strtok($readme, "\n");

        expect($title)->toStartWith('# App Analytics & Remote Config for NativePHP')
            ->and($readme)->toContain('vipertecpro is an independent developer.')
            ->toContain('this package is not affiliated with or endorsed by them.')
            ->not->toMatch('/\b(official|certified|partner)\b/i');
    });

    it('lists the 1.0.0 release in the changelog', function () {
        expect(file_get_contents($this->pluginPath.'/CHANGELOG.md'))->toContain('## [1.0.0]');
    });
});
