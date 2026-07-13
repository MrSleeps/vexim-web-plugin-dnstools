<?php

namespace VEximweb\Plugin\DnsTools\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use VEximweb\Core\Data\Models\Domain;

class MtaStsCheck extends Model
{
    protected $table = 'vw_mta_sts_checks';
    
    protected $fillable = [
        'domain',
        'domain_id',
        'checked_at',
        'next_check_at',
        'dns_valid',
        'dns_policy',
        'dns_mode',
        'dns_mx',
        'dns_max_age',
        'dns_expires_at',
        'policy_valid',
        'policy_data',
        'policy_fetched_at',
        'mx_mismatch',
        'mx_validation_details',
        'error_message',
        'raw_data',
    ];
    
    protected $casts = [
        'checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'dns_expires_at' => 'datetime',
        'policy_fetched_at' => 'datetime',
        'policy_data' => 'array',
        'mx_validation_details' => 'array',
        'raw_data' => 'array',
        'dns_valid' => 'boolean',
        'policy_valid' => 'boolean',
        'mx_mismatch' => 'boolean',
        'dns_max_age' => 'integer',
    ];
    
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_id', 'domain_id');
    }
    
    public function scopeValidDns($query)
    {
        return $query->where('dns_valid', true);
    }
    
    public function scopeValidPolicy($query)
    {
        return $query->where('policy_valid', true);
    }
    
    public function scopeWithMismatch($query)
    {
        return $query->where('mx_mismatch', true);
    }
    
    public function scopeNeedsUpdate($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('checked_at')
              ->orWhere('next_check_at', '<=', now())
              // Also check domains with invalid policies that need re-checking
              ->orWhere(function ($sub) {
                  $sub->where('policy_valid', false)
                      ->whereNotNull('policy_fetched_at');
              })
              // Also check domains with invalid DNS that haven't been checked in the last hour
              ->orWhere(function ($sub) {
                  $sub->where('dns_valid', false)
                      ->where('checked_at', '<=', now()->subHour());
              });
        });
    }
    
    public function scopeExpired($query)
    {
        return $query->whereNotNull('dns_expires_at')
                     ->where('dns_expires_at', '<', now());
    }
    
    public function scopeForDomain($query, $domain)
    {
        return $query->where('domain', $domain);
    }
    
    public function getPolicyFileUrl(): string
    {
        return "https://mta-sts.{$this->domain}/.well-known/mta-sts.txt";
    }
    
    public function isExpired(): bool
    {
        return $this->dns_expires_at && $this->dns_expires_at < now();
    }
    
    public function getModeLabel(): string
    {
        return match($this->dns_mode) {
            'enforce' => 'Enforce (Strict)',
            'testing' => 'Testing',
            'none' => 'None (Disabled)',
            default => $this->dns_mode ?? 'Unknown',
        };
    }
}