<?php

return [
    // Only used when an authorized Admin opens attendance details, never during check-in/out.
    'enabled' => env('DPE_ATTENDANCE_GEOCODER_ENABLED', true),
    'endpoint' => env('DPE_ATTENDANCE_GEOCODER_URL', 'https://photon.komoot.io/reverse'),
    'cache_store' => env('DPE_ATTENDANCE_GEOCODER_CACHE_STORE', 'file'),
    'cache_seconds' => 604800,
    'failure_cache_seconds' => 60,
];
