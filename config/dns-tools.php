<?php

declare(strict_types=1);

$nameservers = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('DNS_TOOLS_NAMESERVERS', '1.1.1.1,1.0.0.1')),
)));

return [
    /*
    |--------------------------------------------------------------------------
    | Public DNS resolvers
    |--------------------------------------------------------------------------
    |
    | DNS Tools performs public DNS checks directly against these recursive
    | resolvers instead of PHP's system resolver. This avoids local /etc/hosts
    | or NSS overrides changing the result of public DNS checks.
    |
    */
    'nameservers' => $nameservers ?: ['1.1.1.1', '1.0.0.1'],

    /*
    |--------------------------------------------------------------------------
    | DNS query timeout
    |--------------------------------------------------------------------------
    */
    'timeout' => (float) env('DNS_TOOLS_TIMEOUT', 5.0),
];
