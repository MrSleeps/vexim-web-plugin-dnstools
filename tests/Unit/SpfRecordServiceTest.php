<?php

use VEximweb\Plugin\DnsTools\Services\DirectDnsResolver;
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
        ->and($result['modifiers'][0]['type'] ?? null)->toBe('redirect')
        ->and($result['modifiers'][0]['domain'] ?? null)->toBe('_spf.example.net');
});

it('accepts SPF without an explicit all mechanism but warns about implicit neutral', function () {
    $result = spfParserForTest()->parseForTest('v=spf1 ip4:192.0.2.10');

    $messages = array_column($result['validation_issues'], 'message');

    expect($result['valid'])->toBeTrue()
        ->and($result['policy'])->toBeNull()
        ->and($messages)->toContain('No explicit all mechanism or redirect; unmatched senders receive the implicit neutral result.');
});

it('still rejects fatal semantic errors', function () {
    $result = spfParserForTest()->parseForTest('v=spf1 redirect=_spf.example.net redirect=_spf2.example.net');

    expect($result['valid'])->toBeFalse();
});


function spfDnsLookupForTest(array $txtRecords): SpfRecordService
{
    $resolver = new class($txtRecords) extends DirectDnsResolver
    {
        public function __construct(private array $txtRecords)
        {
        }

        public function txt(string $domain): array
        {
            return $this->txtRecords;
        }
    };

    return new class($resolver) extends SpfRecordService
    {
        public function lookupForTest(string $domain = 'example.com'): ?string
        {
            return $this->getDnsSpfRecord($domain);
        }
    };
}

it('reads SPF from the direct DNS resolver instead of the system resolver', function () {
    $service = spfDnsLookupForTest([
        'google-site-verification=abc123',
        'v=spf1 mx -all',
    ]);

    expect($service->lookupForTest('caesar.trufflemonkey.co.uk'))
        ->toBe('v=spf1 mx -all');
});

it('still rejects multiple SPF records returned by direct DNS', function () {
    $service = spfDnsLookupForTest([
        'v=spf1 mx -all',
        'V=SPF1 ip4:192.0.2.10 -all',
    ]);

    expect(fn () => $service->lookupForTest())
        ->toThrow(RuntimeException::class, 'Multiple SPF records found for domain');
});
