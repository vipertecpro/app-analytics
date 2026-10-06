<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | `log_in_debug` writes every analytics call to the Laravel log as well,
    | which helps while you check events in the Firebase DebugView.
    |
    */

    'analytics' => [
        'log_in_debug' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote Config
    |--------------------------------------------------------------------------
    |
    | `defaults` are served until the first successful fetch, when the app
    | has no Firebase config, and for any key the server does not define.
    | Values may be strings, numbers, booleans or arrays (read arrays with
    | RemoteConfig::json()).
    |
    | `minimum_fetch_interval` (seconds) throttles fetchAndActivate();
    | Firebase recommends 12 hours in production and allows 0 while testing.
    |
    */

    'remote_config' => [
        'defaults' => [
            // 'new_checkout' => false,
            // 'welcome_title' => 'Welcome!',
            // 'max_items' => 20,
        ],

        'minimum_fetch_interval' => 3600,
    ],

];
