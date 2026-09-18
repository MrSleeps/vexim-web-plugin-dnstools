<?php

use NetDNS2\RR;
use VEximweb\Plugin\DnsTools\Services\DnsResolverService;

function dnsResolverWithRecords(array $records): DnsResolverService
{
    return new class($records) extends DnsResolverService
    {
        public function __construct(private array $records)
        {
        }

        protected function query(string $domain, string $type): array
        {
            return $this->records;
        }
    };
}

it('normalizes TXT records into dns_get_record compatible data', function () {
    $rr = RR::fromString('mail.example.com. 3600 IN TXT "v=spf1 mx -all"');

    $records = dnsResolverWithRecords([$rr])->txt('mail.example.com');

    expect($records)->toHaveCount(1)
        ->and($records[0]['host'])->toBe('mail.example.com')
        ->and($records[0]['ttl'])->toBe(3600)
        ->and($records[0]['type'])->toBe('TXT')
        ->and($records[0]['txt'])->toBe('v=spf1 mx -all')
        ->and($records[0]['entries'])->toBe(['v=spf1 mx -all']);
});

it('normalizes MX records into the keys used by MTA-STS checks', function () {
    $rr = RR::fromString('example.com. 300 IN MX 10 mail.example.com.');

    $records = dnsResolverWithRecords([$rr])->mx('example.com');

    expect($records)->toHaveCount(1)
        ->and($records[0]['target'])->toBe('mail.example.com')
        ->and($records[0]['pri'])->toBe(10)
        ->and($records[0]['ttl'])->toBe(300);
});

it('normalizes CNAME records into the keys used by MTA-STS checks', function () {
    $rr = RR::fromString('mta-sts.example.com. 300 IN CNAME sts.example.net.');

    $records = dnsResolverWithRecords([$rr])->cname('mta-sts.example.com');

    expect($records)->toHaveCount(1)
        ->and($records[0]['target'])->toBe('sts.example.net')
        ->and($records[0]['host'])->toBe('mta-sts.example.com')
        ->and($records[0]['ttl'])->toBe(300);
});
