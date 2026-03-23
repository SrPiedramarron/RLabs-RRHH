<?php
return [
    'url'          => env('FACIAL_URL',          'http://127.0.0.1:5001'),
    'secret'       => env('FACIAL_SECRET',        'rlabs_facial_2026'),
    'timeout'      => env('FACIAL_TIMEOUT',        15),
    'min_confianza'=> env('FACIAL_MIN_CONFIANZA',  0.50),
];
