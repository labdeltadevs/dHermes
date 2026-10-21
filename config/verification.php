<?php

return [
    'key' => env('VERIFICATION_HMAC_KEY', env('APP_KEY')),
    'code_length' => 32,
];
