<?php

namespace VEximweb\Plugin\DnsTools\Models;

use Illuminate\Database\Eloquent\Model;

class SpfCheck extends Model
{
    protected $table = 'vw_spf_checks';
    
    protected $fillable = [
        'domain',
        'domain_id',
        'record',
        'spf_version',
        'policy',
        'lookup_count',
        'mechanisms',
        'includes',
        'ip4',
        'ip6',
        'mx_domains',
        'a_domains',
        'modifiers',
        'has_ptr',
        'has_exists',
        'valid',
        'error_message',
        'validation_issues',
        'mechanism_count',
        'last_checked_at',
        'next_check_at',
    ];
    
    protected $casts = [
        'mechanisms' => 'array',
        'includes' => 'array',
        'ip4' => 'array',
        'ip6' => 'array',
        'mx_domains' => 'array',
        'a_domains' => 'array',
        'modifiers' => 'array',
        'validation_issues' => 'array',
        'valid' => 'boolean',
        'has_ptr' => 'boolean',
        'has_exists' => 'boolean',
        'lookup_count' => 'integer',
        'mechanism_count' => 'integer',
        'last_checked_at' => 'datetime',
        'next_check_at' => 'datetime',
    ];
    
    /**
     * Get the domain relationship
     */
    public function domain()
    {
        return $this->belongsTo(SystemDomains::class, 'domain_id', 'domain_id');
    }
    
    /**
     * Get human-readable policy label
     */
    public function getPolicyLabel(): string
    {
        return match($this->policy) {
            '-all' => 'Hard Fail (Reject)',
            '~all' => 'Soft Fail (Mark)',
            '?all' => 'Neutral',
            '+all' => 'Pass (DANGEROUS)',
            default => $this->policy ?? 'Unknown',
        };
    }
    
    /**
     * Check if the SPF record has too many lookups
     */
    public function hasTooManyLookups(): bool
    {
        return $this->lookup_count > 10;
    }
    
    /**
     * Get warning messages for the SPF record
     */
    public function getWarnings(): array
    {
        $warnings = [];
        
        if ($this->hasTooManyLookups()) {
            $warnings[] = "SPF record has {$this->lookup_count} DNS lookups (maximum recommended is 10)";
        }
        
        if ($this->has_ptr) {
            $warnings[] = "PTR mechanism is deprecated and should be avoided";
        }
        
        if ($this->policy === '+all') {
            $warnings[] = "WARNING: +all policy allows any server to send email from this domain";
        }
        
        if ($this->policy === '?all') {
            $warnings[] = "?all policy provides no authentication (neutral)";
        }
        
        if (!empty($this->validation_issues)) {
            $issues = is_array($this->validation_issues) ? $this->validation_issues : json_decode($this->validation_issues, true);
            if (is_array($issues)) {
                foreach ($issues as $issue) {
                    if (isset($issue['level']) && $issue['level'] === 'warning') {
                        $warnings[] = $issue['message'];
                    }
                }
            }
        }
        
        return $warnings;
    }
    
    /**
     * Get error messages for the SPF record
     */
    public function getErrors(): array
    {
        $errors = [];
        
        if (!$this->valid) {
            $errors[] = $this->error_message ?? 'SPF record is invalid';
        }
        
        if (!empty($this->validation_issues)) {
            $issues = is_array($this->validation_issues) ? $this->validation_issues : json_decode($this->validation_issues, true);
            if (is_array($issues)) {
                foreach ($issues as $issue) {
                    if (isset($issue['level']) && $issue['level'] === 'error') {
                        $errors[] = $issue['message'];
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Scope a query to only include valid SPF records
     */
    public function scopeValid($query)
    {
        return $query->where('valid', true);
    }
    
    /**
     * Scope a query to only include invalid SPF records
     */
    public function scopeInvalid($query)
    {
        return $query->where('valid', false);
    }
    
    /**
     * Scope a query to only include records with no SPF record
     */
    public function scopeNoRecord($query)
    {
        return $query->whereNull('record');
    }
    
    /**
     * Scope a query to only include records with high lookup counts
     */
    public function scopeHighLookups($query, int $threshold = 10)
    {
        return $query->where('lookup_count', '>', $threshold);
    }
}