<?php

return [

    // Live Expo tunnel from this computer. It changes if that process restarts.
    'app_store_url' => env('APP_STORE_URL') ?: 'exp://jvjzxya-devxnetx-8081.exp.direct',

    // Android internal APK. Expo deletes this file on 2026-11-30.
    'play_store_url' => env('PLAY_STORE_URL') ?: 'https://expo.dev/artifacts/eas/LEjq1XFy2T8F_lM0ipt9tkKzhwKxf0Ch2bROjNcnBis.apk',

    // Gap between requests to the sports calendar. Tests set this to 0.
    'sporten_pause_ms' => (int) env('SPORTEN_PAUSE_MS', 1500),

    // Gap between EventiBG day pages. How many days ahead is an admin setting.
    'eventibg_pause_ms' => (int) env('EVENTIBG_PAUSE_MS', 1500),
    'eventibg_days' => (int) env('EVENTIBG_DAYS', 40),

    // Gap between Aktualnite Novini pages. How many days ahead is the shared setting.
    'aktualnitenovini_pause_ms' => (int) env('AKTUALNITENOVINI_PAUSE_MS', 1500),

    // Gap between Kupi Bileti pages. That import reads every upcoming event.
    'kupibileti_pause_ms' => (int) env('KUPIBILETI_PAUSE_MS', 1500),

    // Gap between Facebook event reads. A free Apify account still stops at 5 events per call.
    'facebook_pause_ms' => (int) env('FACEBOOK_PAUSE_MS', 1500),
    'facebook_events_max_per_city' => 15,
    'facebook_cities' => [
        [
            'name' => 'Стара Загора',
            'url' => 'https://www.facebook.com/events/explore/stara-zagora-bulgaria/108597975837530',
            'query' => 'Stara Zagora',
            'city_id' => '108597975837530',
            'latitude' => 42.4258,
            'longitude' => 25.6345,
            'match' => ['stara zagora', 'стара загора'],
        ],
        [
            'name' => 'Варна',
            'url' => 'https://www.facebook.com/events/explore/varna-bulgaria/114974968516694',
            'query' => 'Varna',
            'city_id' => '114974968516694',
            'latitude' => 43.2141,
            'longitude' => 27.9147,
            'match' => ['varna', 'варна'],
        ],
    ],

    // Gap between address lookups. Nominatim asks for at most one a second.
    'nominatim_pause_ms' => (int) env('NOMINATIM_PAUSE_MS', 1100),

];
