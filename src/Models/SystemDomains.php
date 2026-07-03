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
     * Get the MTA-STS check for this domain
     */
    public function mtaStsCheck()
    {
        return $this->hasOne(MtaStsCheck::class, 'domain_id', 'domain_id');
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
     * Check if domain has a valid MTA-STS configuration
     */
    public function hasValidMtaSts(): bool
    {
        $mtaSts = $this->mtaStsCheck()->first();
        return $mtaSts && $mtaSts->dns_valid && $mtaSts->policy_valid;
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
     * Get MTA-STS status
     */
    public function getMtaStsStatus(): string
    {
        $mtaSts = $this->mtaStsCheck()->first();
        if (!$mtaSts) {
            return 'not_checked';
        }
        
        if ($mtaSts->dns_valid && $mtaSts->policy_valid) {
            return 'valid';
        }
        
        if ($mtaSts->dns_valid && !$mtaSts->policy_valid) {
            return 'partial';
        }
        
        return 'invalid';
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
    
    /**
     * Get MTA-STS mode if available
     */
    public function getMtaStsMode(): ?string
    {
        $mtaSts = $this->mtaStsCheck()->first();
        return $mtaSts ? $mtaSts->dns_mode : null;
    }
    
    /**
     * Get MTA-STS mode label
     */
    public function getMtaStsModeLabel(): string
    {
        $mtaSts = $this->mtaStsCheck()->first();
        if (!$mtaSts) {
            return 'Not configured';
        }
        return $mtaSts->getModeLabel();
    }
    
    /**
     * Check if MTA-STS has MX mismatch
     */
    public function hasMtaStsMxMismatch(): bool
    {
        $mtaSts = $this->mtaStsCheck()->first();
        return $mtaSts && $mtaSts->mx_mismatch;
    }
    
    /**
     * Get MTA-STS policy age
     */
    public function getMtaStsPolicyAge(): ?string
    {
        $mtaSts = $this->mtaStsCheck()->first();
        if (!$mtaSts || !$mtaSts->policy_fetched_at) {
            return null;
        }
        return $mtaSts->policy_fetched_at->diffForHumans();
    }
}