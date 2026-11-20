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
    'eventibg_days' => (int) env('EVENTIBG_DAYS', 14),

    // Gap between address lookups. Nominatim asks for at most one a second.
    'nominatim_pause_ms' => (int) env('NOMINATIM_PAUSE_MS', 1100),

];
