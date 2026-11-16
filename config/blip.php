<?php

return [

    // Live Expo tunnel from this computer. It changes if that process restarts.
    'app_store_url' => env('APP_STORE_URL') ?: 'exp://jvjzxya-devxnetx-8081.exp.direct',

    // Android internal APK. Expo deletes this file on 2026-11-30.
    'play_store_url' => env('PLAY_STORE_URL') ?: 'https://expo.dev/artifacts/eas/LEjq1XFy2T8F_lM0ipt9tkKzhwKxf0Ch2bROjNcnBis.apk',

];
