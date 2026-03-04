<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL del microservicio de validación facial
    |--------------------------------------------------------------------------
    | Solo debe ser accesible desde localhost. Nunca exponer al exterior.
    */
    'url' => env('FACIAL_SERVICE_URL', 'http://127.0.0.1:5001'),

    /*
    |--------------------------------------------------------------------------
    | Clave secreta compartida con el microservicio Python
    |--------------------------------------------------------------------------
    | Debe coincidir con la variable FACIAL_SECRET en el entorno del servicio.
    */
    'secret' => env('FACIAL_SERVICE_SECRET', 'rlabs_facial_2026'),

    /*
    |--------------------------------------------------------------------------
    | Timeout en segundos para las peticiones al microservicio
    |--------------------------------------------------------------------------
    */
    'timeout' => env('FACIAL_SERVICE_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Umbral de confianza mínima para aprobar un checkin
    |--------------------------------------------------------------------------
    | Valor entre 0 y 1. Por defecto 0.50 (50% de similitud mínima).
    | El microservicio tiene su propio threshold, este es un filtro adicional.
    */
    'min_confianza' => env('FACIAL_MIN_CONFIANZA', 0.50),

];
