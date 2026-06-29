<?php

namespace VEximweb\Plugin\DnsTools\Dmarc\Services;

use VEximweb\Plugin\DnsTools\Dmarc\DmarcRecord;
use VEximweb\Plugin\DnsTools\Dmarc\Exceptions\InvalidDmarcRecordException;
use VEximweb\Plugin\DnsTools\Models\DmarcCheck;
use Illuminate\Support\Facades\Log;

class DmarcCheckService
{
    /**
     * Check a single domain's DMARC record
     */
    public function checkDomain(string $domain, ?int $domainId = null): ?DmarcCheck
    {
        $startTime = microtime(true);
        $attempt = 1;
        $maxAttempts = 3;
        $lastError = null;
        
        Log::info('DMARC check started', [
            'domain' => $domain,
            'domain_id' => $domainId,
            'timestamp' => now()->toDateTimeString()
        ]);
        
        while ($attempt <= $maxAttempts) {
            $attemptStart = microtime(true);
            
            try {
                Log::debug('DMARC check attempt', [
                    'domain' => $domain,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts
                ]);
                
                // Add delay between attempts (except first)
                if ($attempt > 1) {
                    $delay = 1;
                    Log::debug('Adding delay before retry', [
                        'domain' => $domain,
                        'attempt' => $attempt,
                        'delay_seconds' => $delay
                    ]);
                    sleep($delay);
                }
                
                // Increase DNS timeout for remote domains
                $originalTimeout = ini_get('dns.timeout');
                $originalRetry = ini_get('dns.retry');
                
                Log::debug('DNS settings before change', [
                    'domain' => $domain,
                    'original_timeout' => $originalTimeout,
                    'original_retry' => $originalRetry
                ]);
                
                ini_set('dns.timeout', '10');
                ini_set('dns.retry', '5');
                
                Log::debug('DNS settings after change', [
                    'domain' => $domain,
                    'new_timeout' => ini_get('dns.timeout'),
                    'new_retry' => ini_get('dns.retry')
                ]);
                
                // Query DNS for DMARC record
                $dnsStart = microtime(true);
                $records = @dns_get_record("_dmarc.{$domain}", DNS_TXT);
                $dnsDuration = microtime(true) - $dnsStart;
                
                // Restore original settings
                if ($originalTimeout !== false) {
                    ini_set('dns.timeout', $originalTimeout);
                }
                if ($originalRetry !== false) {
                    ini_set('dns.retry', $originalRetry);
                }
                
                Log::debug('DNS query completed', [
                    'domain' => $domain,
                    'attempt' => $attempt,
                    'duration_ms' => round($dnsDuration * 1000, 2),
                    'success' => $records !== false,
                    'record_count' => $records ? count($records) : 0,
                    'records_found' => $records ? array_column($records, 'txt') : []
                ]);
                
                // If DNS query failed or returned no records
                if ($records === false) {
                    $lastError = 'DNS query returned false (error)';
                    Log::warning('DNS query returned false', [
                        'domain' => $domain,
                        'attempt' => $attempt,
                        'duration_ms' => round($dnsDuration * 1000, 2)
                    ]);
                    $attempt++;
                    continue;
                }
                
                if (empty($records)) {
                    $lastError = 'No DMARC record found (empty response)';
                    Log::warning('DNS query returned empty', [
                        'domain' => $domain,
                        'attempt' => $attempt,
                        'duration_ms' => round($dnsDuration * 1000, 2)
                    ]);
                    $attempt++;
                    continue;
                }
                
                // Find the DMARC record (starts with v=DMARC1)
                $dmarcRecord = null;
                foreach ($records as $record) {
                    $txt = $record['txt'] ?? '';
                    if (str_starts_with(trim($txt), 'v=DMARC1')) {
                        $dmarcRecord = $txt;
                        Log::debug('Found DMARC record', [
                            'domain' => $domain,
                            'attempt' => $attempt,
                            'record' => $dmarcRecord
                        ]);
                        break;
                    }
                }
                
                if (!$dmarcRecord) {
                    $lastError = 'No valid DMARC record found (v=DMARC1 missing)';
                    Log::warning('No DMARC record with v=DMARC1 found', [
                        'domain' => $domain,
                        'attempt' => $attempt,
                        'records' => array_column($records, 'txt')
                    ]);
                    $attempt++;
                    continue;
                }
                
                // Parse the DMARC record
                Log::debug('Parsing DMARC record', [
                    'domain' => $domain,
                    'attempt' => $attempt,
                    'record' => $dmarcRecord
                ]);
                
                try {
                    $parsed = DmarcRecord::fromString($dmarcRecord);
                } catch (InvalidDmarcRecordException $e) {
                    $lastError = 'Invalid DMARC record: ' . $e->getMessage();
                    Log::warning('Invalid DMARC record', [
                        'domain' => $domain,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'record' => $dmarcRecord
                    ]);
                    $attempt++;
                    continue;
                }
                
                // Save to cache
                $totalDuration = microtime(true) - $startTime;
                
                Log::info('DMARC check successful', [
                    'domain' => $domain,
                    'attempt' => $attempt,
                    'total_duration_ms' => round($totalDuration * 1000, 2),
                    'dns_duration_ms' => round($dnsDuration * 1000, 2),
                    'policy' => $parsed->getPolicy()?->value,
                    'record' => $dmarcRecord
                ]);
                
                return $this->saveSuccessfulCheck($domain, $domainId, $dmarcRecord, $parsed);
                
            } catch (InvalidDmarcRecordException $e) {
                $lastError = 'Invalid DMARC record: ' . $e->getMessage();
                Log::warning('DMARC check attempt failed (InvalidDmarcRecordException)', [
                    'domain' => $domain,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);
                $attempt++;
                continue;
            } catch (\Exception $e) {
                $lastError = 'Error checking DMARC: ' . $e->getMessage();
                Log::warning('DMARC check attempt failed (Exception)', [
                    'domain' => $domain,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $attempt++;
                continue;
            }
        }
        
        // All attempts failed
        $totalDuration = microtime(true) - $startTime;
        
        Log::error('DMARC check failed after all attempts', [
            'domain' => $domain,
            'attempts' => $attempt - 1,
            'total_duration_ms' => round($totalDuration * 1000, 2),
            'last_error' => $lastError,
            'timestamp' => now()->toDateTimeString()
        ]);
        
        return $this->saveFailedCheck($domain, $domainId, $lastError ?? 'Unknown error after ' . ($attempt - 1) . ' attempts');
    }
    
    /**
     * Check all domains from the domains table
     */
    public function checkAllDomains(): void
    {
        Log::info('Starting DMARC check for all domains');
        
        // Get domains from the domains table
        $domains = \VEximweb\Core\Data\Models\Domain::where('enabled', true)
            ->select('domain_id', 'domain')
            ->get();
            
        Log::info('Found domains to check', [
            'total' => $domains->count()
        ]);
            
        foreach ($domains as $domain) {
            Log::debug('Checking domain', [
                'domain' => $domain->domain,
                'domain_id' => $domain->domain_id
            ]);
            $this->checkDomain($domain->domain, $domain->domain_id);
        }
        
        Log::info('Finished DMARC check for all domains');
    }
    
    /**
     * Check domains that need checking (next_check_at <= now)
     */
    public function checkDomainsNeedingUpdate(): void
    {
        Log::info('Starting DMARC check for domains needing update');
        
        // First, get all domains from the domains table
        $domains = \VEximweb\Core\Data\Models\Domain::where('enabled', true)
            ->select('domain_id', 'domain')
            ->get();
            
        $checked = 0;
        $skipped = 0;
            
        foreach ($domains as $domain) {
            // Check if this domain needs updating
            $cache = DmarcCheck::where('domain', $domain->domain)->first();
            
            if (!$cache || ($cache->next_check_at && $cache->next_check_at <= now())) {
                Log::debug('Domain needs update', [
                    'domain' => $domain->domain,
                    'last_check' => $cache?->last_checked_at,
                    'next_check' => $cache?->next_check_at
                ]);
                $this->checkDomain($domain->domain, $domain->domain_id);
                $checked++;
            } else {
                Log::debug('Domain skipped (not due for update)', [
                    'domain' => $domain->domain,
                    'next_check' => $cache->next_check_at
                ]);
                $skipped++;
            }
        }
        
        Log::info('Finished DMARC check for domains needing update', [
            'checked' => $checked,
            'skipped' => $skipped,
            'total' => $domains->count()
        ]);
    }
    
    /**
     * Save a successful check
     */
    protected function saveSuccessfulCheck(string $domain, ?int $domainId, string $record, DmarcRecord $parsed): DmarcCheck
    {
        Log::debug('Saving successful check', [
            'domain' => $domain,
            'policy' => $parsed->getPolicy()?->value
        ]);
        
        $result = DmarcCheck::updateOrCreate(
            ['domain' => $domain],
            [
                'domain_id' => $domainId,
                'record' => $record,
                'policy' => $parsed->getPolicy()?->value,
                'subdomain_policy' => $parsed->getSubdomainPolicy()?->value,
                'adkim' => $parsed->getAdkim()?->value,
                'aspf' => $parsed->getAspf()?->value,
                'rua' => $parsed->getRua() ? explode(',', $parsed->getRua()) : null,
                'ruf' => $parsed->getRuf() ? explode(',', $parsed->getRuf()) : null,
                'reporting' => $parsed->getReporting() ? array_map(fn($r) => $r->value, $parsed->getReporting()) : null,
                'percentage' => $parsed->getPercentage(),
                'report_interval' => $parsed->getReportInterval(),
                'np' => $parsed->getNonExistentSubdomainPolicy()?->value,
                'psd' => $parsed->getPublicSuffixDomainPolicy(),
                't' => $parsed->getTestingMode()?->value,
                'valid' => true,
                'error_message' => null,
                'last_checked_at' => now(),
                'next_check_at' => now()->addHours(24),
            ]
        );
        
        Log::debug('Saved successful check', [
            'domain' => $domain,
            'id' => $result->id,
            'valid' => $result->valid
        ]);
        
        return $result;
    }
    
    /**
     * Save a failed check
     */
    protected function saveFailedCheck(string $domain, ?int $domainId, string $error): DmarcCheck
    {
        Log::debug('Saving failed check', [
            'domain' => $domain,
            'error' => $error
        ]);
        
        $result = DmarcCheck::updateOrCreate(
            ['domain' => $domain],
            [
                'domain_id' => $domainId,
                'valid' => false,
                'error_message' => $error,
                'last_checked_at' => now(),
                'next_check_at' => now()->addHours(6),
            ]
        );
        
        Log::debug('Saved failed check', [
            'domain' => $domain,
            'id' => $result->id,
            'valid' => $result->valid,
            'error' => $result->error_message
        ]);
        
        return $result;
    }
    
    /**
     * Clear cache for a domain
     */
    public function clearCache(string $domain): void
    {
        Log::info('Clearing DMARC cache', ['domain' => $domain]);
        DmarcCheck::where('domain', $domain)->delete();
        Log::debug('DMARC cache cleared', ['domain' => $domain]);
    }
    
    /**
     * Get cached DMARC record for a domain
     */
    public function getCached(string $domain): ?DmarcCheck
    {
        Log::debug('Getting cached DMARC record', ['domain' => $domain]);
        
        $cache = DmarcCheck::where('domain', $domain)->first();
        
        if (!$cache) {
            Log::debug('No cached record found, checking domain', ['domain' => $domain]);
            return $this->checkDomain($domain);
        }
        
        if ($cache->next_check_at && $cache->next_check_at <= now()) {
            Log::debug('Cached record expired, rechecking', [
                'domain' => $domain,
                'next_check' => $cache->next_check_at,
                'now' => now()
            ]);
            return $this->checkDomain($domain);
        }
        
        Log::debug('Returning cached record', [
            'domain' => $domain,
            'valid' => $cache->valid,
            'policy' => $cache->policy
        ]);
        
        return $cache;
    }
    
    /**
     * Get statistics about DMARC records
     */
    public function getStats(): array
    {
        Log::debug('Getting DMARC statistics');
        
        $total = DmarcCheck::count();
        $valid = DmarcCheck::where('valid', true)->count();
        $invalid = DmarcCheck::where('valid', false)->count();
        
        $policies = DmarcCheck::where('valid', true)
            ->select('policy', \DB::raw('count(*) as count'))
            ->groupBy('policy')
            ->pluck('count', 'policy')
            ->toArray();
        
        $noRecord = DmarcCheck::where('valid', false)
            ->where('error_message', 'No DMARC record found')
            ->count();
        
        $stats = [
            'total' => $total,
            'valid' => $valid,
            'invalid' => $invalid,
            'policies' => $policies,
            'no_record' => $noRecord,
        ];
        
        Log::debug('DMARC statistics', $stats);
        
        return $stats;
    }
}