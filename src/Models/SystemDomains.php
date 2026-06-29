<?php

namespace VEximweb\Plugin\DnsTools\Models;

use VEximweb\Core\Data\Models\Domain as BaseDomain;
use VEximweb\Plugin\DnsTools\Models\DmarcCheck;

class SystemDomains extends BaseDomain
{
    /**
     * Get the DMARC check for this domain
     */
    public function dmarcCheck()
    {
        return $this->hasOne(DmarcCheck::class, 'domain_id', 'domain_id');
    }
    
    /**
     * Check if domain has a valid DMARC record
     */
    public function hasValidDmarc(): bool
    {
        $dmarc = $this->dmarcCheck()->first();
        return $dmarc && $dmarc->valid;
    }
    
    /**
     * Get DMARC status
     */
    public function getDmarcStatus(): string
    {
        $dmarc = $this->dmarcCheck()->first();
        if (!$dmarc) {
            return 'not_checked';
        }
        return $dmarc->valid ? 'valid' : 'invalid';
    }
}