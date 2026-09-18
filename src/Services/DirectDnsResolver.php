<?php

declare(strict_types=1);

namespace VEximweb\Plugin\DnsTools\Services;

use InvalidArgumentException;
use NetDNS2\ENUM\Error;
use NetDNS2\Exception as DnsException;
use NetDNS2\Resolver;
use NetDNS2\RR\TXT;

class DirectDnsResolver
{
    private Resolver $resolver;

    /**
     * @param array<int, string>|null $nameservers
     */
    public function __construct(?array $nameservers = null, float $timeout = 5.0)
    {
        $nameservers ??= ['1.1.1.1', '1.0.0.1'];

        $nameservers = array_values(array_filter(array_map(
            static fn (mixed $server): string => trim((string) $server),
            $nameservers,
        )));

        if ($nameservers === []) {
            throw new InvalidArgumentException('At least one DNS nameserver must be configured.');
        }

        $this->resolver = new Resolver([
            'nameservers' => $nameservers,
            'ns_random' => true,
            'timeout' => max(0.1, $timeout),
        ]);
    }

    /**
     * Query TXT records directly from the configured recursive DNS servers.
     *
     * This intentionally bypasses PHP's dns_get_record()/system resolver path,
     * so local /etc/hosts entries and NSS hostname overrides cannot hide public
     * TXT records.
     *
     * @return array<int, string>
     *
     * @throws DnsException
     */
    public function txt(string $domain): array
    {
        $domain = rtrim(trim($domain), '.');

        if ($domain === '') {
            throw new InvalidArgumentException('DNS domain cannot be empty.');
        }

        try {
            $response = $this->resolver->query($domain, 'TXT');
        } catch (DnsException $e) {
            if ($e->getCode() === Error::DNS_NXDOMAIN->value) {
                return [];
            }

            throw $e;
        }

        $records = [];

        foreach ($response->answer as $answer) {
            if (! $answer instanceof TXT) {
                continue;
            }

            $chunks = [];

            foreach ($answer->text as $chunk) {
                $chunks[] = $chunk->value();
            }

            // RFC 1035 permits one logical TXT RR to be split into multiple
            // character-strings. SPF consumes the concatenated value.
            $records[] = implode('', $chunks);
        }

        return $records;
    }
}
