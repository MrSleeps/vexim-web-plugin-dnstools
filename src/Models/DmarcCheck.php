<?php

namespace VEximweb\Plugin\DnsTools\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached DMARC records for domains
 */
class DmarcCheck extends Model
{
    protected $table = 'vw_dmarc_checks';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'domain',
        'domain_id',  // Foreign key to domains.domain_id (just for reference)
        'record',
        'policy',
        'subdomain_policy',
        'adkim',
        'aspf',
        'rua',
        'ruf',
        'reporting',
        'percentage',
        'report_interval',
        'np',
        'psd',
        't',
        'valid',
        'error_message',
        'last_checked_at',
        'next_check_at',
    ];
    
    protected $casts = [
        'valid' => 'boolean',
        'last_checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'percentage' => 'integer',
        'report_interval' => 'integer',
        'rua' => 'array',
        'ruf' => 'array',
        'reporting' => 'array',
    ];
    
    /**
     * Get the domain this DMARC check belongs to (optional relation)
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(\VEximweb\Core\Data\Models\Domain::class, 'domain_id', 'domain_id');
    }
    
    /**
     * Check if this domain has a DMARC record
     */
    public function hasRecord(): bool
    {
        return $this->valid && !empty($this->record);
    }
    
    /**
     * Get the DMARC record as a string
     */
    public function getRecordString(): ?string
    {
        return $this->record;
    }
    
    /**
     * Get the policy with a nice label
     */
    public function getPolicyLabel(): string
    {
        $labels = [
            'none' => 'Monitor',
            'quarantine' => 'Quarantine',
            'reject' => 'Reject',
        ];
        
        return $labels[$this->policy] ?? 'Unknown';
    }
    
    /**
     * Get the policy with a color
     */
    public function getPolicyColor(): string
    {
        return match($this->policy) {
            'none' => 'success',
            'quarantine' => 'warning',
            'reject' => 'danger',
            default => 'gray',
        };
    }
    
    /**
     * Get policy icon
     */
    public function getPolicyIcon(): string
    {
        return match($this->policy) {
            'none' => 'heroicon-o-eye',
            'quarantine' => 'heroicon-o-shield-exclamation',
            'reject' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }
    
    
}