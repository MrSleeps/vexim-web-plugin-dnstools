<?php

declare(strict_types=1);

namespace VEximweb\Plugin\DnsTools\Services;

use Illuminate\Support\Facades\Log;
use NetDNS2\Exception as NetDnsException;
use NetDNS2\Resolver;
use NetDNS2\RR\CNAME;
use NetDNS2\RR\MX;
use NetDNS2\RR\TXT;

class DnsResolverService
{
    /**
     * @var array<int, string>
     */
    protected array $nameservers;

    protected float $timeout;

    /**
     * @param array<int, string>|null $nameservers
     */
    public function __construct(?array $nameservers = null, ?float $timeout = null)
    {
        $this->nameservers = $nameservers ?? (array) config('dns-tools.nameservers', ['1.1.1.1', '1.0.0.1']);
        $this->timeout = $timeout ?? (float) config('dns-tools.timeout', 5.0);
    }

    /**
     * Return TXT records using the same shape as dns_get_record().
     *
     * @return array<int, array{host:string,class:string,ttl:int,type:string,txt:string,entries:array<int,string>}>
     */
    public function txt(string $domain): array
    {
        $records = [];

        foreach ($this->query($domain, 'TXT') as $rr) {
            if (! $rr instanceof TXT) {
                continue;
            }

            $entries = array_map(
                static fn ($part): string => (string) $part,
                (array) $rr->text,
            );

            $records[] = [
                'host' => rtrim((string) $rr->name, '.'),
                'class' => 'IN',
                'ttl' => (int) $rr->ttl,
                'type' => 'TXT',
                'txt' => implode('', $entries),
                'entries' => $entries,
            ];
        }

        return $records;
    }

    /**
     * Return MX records using the same keys consumed by the existing checker.
     *
     * @return array<int, array{host:string,class:string,ttl:int,type:string,pri:int,target:string}>
     */
    public function mx(string $domain): array
    {
        $records = [];

        foreach ($this->query($domain, 'MX') as $rr) {
            if (! $rr instanceof MX) {
                continue;
            }

            $records[] = [
                'host' => rtrim((string) $rr->name, '.'),
                'class' => 'IN',
                'ttl' => (int) $rr->ttl,
                'type' => 'MX',
                'pri' => (int) $rr->preference,
                'target' => rtrim((string) $rr->exchange, '.'),
            ];
        }

        return $records;
    }

    /**
     * Return CNAME records using the same keys consumed by the existing checker.
     *
     * @return array<int, array{host:string,class:string,ttl:int,type:string,target:string}>
     */
    public function cname(string $domain): array
    {
        $records = [];

        foreach ($this->query($domain, 'CNAME') as $rr) {
            if (! $rr instanceof CNAME) {
                continue;
            }

            $records[] = [
                'host' => rtrim((string) $rr->name, '.'),
                'class' => 'IN',
                'ttl' => (int) $rr->ttl,
                'type' => 'CNAME',
                'target' => rtrim((string) $rr->cname, '.'),
            ];
        }

        return $records;
    }

    /**
     * @return array<int, object>
     */
    protected function query(string $domain, string $type): array
    {
        $domain = rtrim(trim($domain), '.');

        if ($domain === '') {
            return [];
        }

        try {
            $resolver = new Resolver([
                'nameservers' => $this->nameservers,
                'ns_random' => true,
                'timeout' => max(0.1, $this->timeout),
            ]);

            $response = $resolver->query($domain, strtoupper($type));

            return $response->answer ?? [];
        } catch (NetDnsException $e) {
            Log::debug('Direct DNS query returned no usable answer', [
                'domain' => $domain,
                'type' => strtoupper($type),
                'nameservers' => $this->nameservers,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
