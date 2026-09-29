<?php

return [
    'proxies' => env('TRUSTED_PROXIES')
        ? array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES'))))
        : null,
];
