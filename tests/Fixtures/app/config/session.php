<?php

return [
    'driver' => 'array',
    'lifetime' => 120,
    'expire_on_close' => false,
    'encrypt' => false,
    'cookie' => 'optimized_test_session',
    'path' => '/',
    'domain' => null,
    'secure' => null,
    'http_only' => true,
    'same_site' => 'lax',
    'lottery' => [0, 100],
];
