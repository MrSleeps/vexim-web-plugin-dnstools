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
     * Get the SPF check for this domain
     */
    public function spfCheck()
    {
        return $this->hasOne(SpfCheck::class, 'domain_id', 'domain_id');
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
     * Check if domain has a valid SPF record
     */
    public function hasValidSpf(): bool
    {
        $spf = $this->spfCheck()->first();
        return $spf && $spf->valid;
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
    
    /**
     * Get SPF status
     */
    public function getSpfStatus(): string
    {
        $spf = $this->spfCheck()->first();
        if (!$spf) {
            return 'not_checked';
        }
        return $spf->valid ? 'valid' : 'invalid';
    }
    
    /**
     * Get DMARC policy if available
     */
    public function getDmarcPolicy(): ?string
    {
        $dmarc = $this->dmarcCheck()->first();
        return $dmarc ? $dmarc->policy : null;
    }
    
    /**
     * Get SPF policy if available
     */
    public function getSpfPolicy(): ?string
    {
        $spf = $this->spfCheck()->first();
        return $spf ? $spf->policy : null;
    }
}