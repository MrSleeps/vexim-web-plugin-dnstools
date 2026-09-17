<?php

use VEximweb\Plugin\DnsTools\Services\SpfRecordService;

function spfParserForTest(): SpfRecordService
{
    return new class extends SpfRecordService
    {
        public function parseForTest(string $record, string $domain = 'example.com'): array
        {
            return $this->parseSpfRecord($record, $domain);
        }
    };
}

it('accepts a redirect-terminated SPF record as valid', function () {
    $result = spfParserForTest()->parseForTest('v=spf1 redirect=_spf.example.net');

    expect($result['valid'])->toBeTrue()
        ->and($result['policy'])->toBeNull()
        ->and($result['modifiers'])->toContainEqual([
            'type' => 'redirect',
            'value' => 'redirect=_spf.example.net',
            'domain' => '_spf.example.net',
        ]);
});

it('accepts SPF without an explicit all mechanism but warns about implicit neutral', function () {
    $result = spfParserForTest()->parseForTest('v=spf1 ip4:192.0.2.10');

    expect($result['valid'])->toBeTrue()
        ->and($result['policy'])->toBeNull()
        ->and(collect($result['validation_issues'])->pluck('message')->all())
        ->toContain('No explicit all mechanism or redirect; unmatched senders receive the implicit neutral result.');
});

it('still rejects fatal semantic errors', function () {
    $result = spfParserForTest()->parseForTest('v=spf1 redirect=_spf.example.net redirect=_spf2.example.net');

    expect($result['valid'])->toBeFalse();
});
