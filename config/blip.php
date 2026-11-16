<?php

return [

    // Published Expo Go updates. A real store listing replaces either value through the env.
    'app_store_url' => env('APP_STORE_URL') ?: 'exp://u.expo.dev/update/01a1e4d4-a46f-7329-b742-e085945831eb',

    'play_store_url' => env('PLAY_STORE_URL') ?: 'exp://u.expo.dev/update/01a1e4d4-a46f-7bac-9b48-48946b24bd2e',

];
