<?php

namespace VEximweb\Plugin\DnsTools\Services;

use CbowOfRivia\DmarcRecordBuilder\DmarcRecord;
use VEximweb\Plugin\DnsTools\Models\SystemDomains as Domain;

class DmarcRecordService
{
    public function build(Domain $domain, array $data): DmarcRecord
    {
        return new DmarcRecord(
            policy: $data['dmarc_policy'],
            subdomain_policy: $data['dmarc_subdomain_policy'] ?: null,
            rua: 'mailto:' . $data['dmarc_rua_localpart'] . '@' . $domain->domain,
            ruf: 'mailto:' . $data['dmarc_ruf_localpart'] . '@' . $domain->domain,
            adkim: $data['dmarc_adkim'],
            aspf: $data['dmarc_aspf'],
            reporting: $data['dmarc_reporting'] ?? [],
            np: $data['dmarc_np'] ?: null,
            psd: $data['dmarc_psd'],
            t: $data['dmarc_t'],
        );
    }

    public function generate(Domain $domain, array $data): string
    {
        return (string) $this->build($domain, $data);
    }
}