<?php

namespace VEximweb\Plugin\DnsTools\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VEximweb\Plugin\DnsTools\Models\MtaStsCheck;
use VEximweb\Core\Data\Models\Domain;

class MtaStsService
{
    /**
     * Check MTA-STS for a specific domain
     */
    public function checkDomain(string $domain, ?int $domainId = null): ?object
    {
        if (!$domainId) {
            $domainModel = Domain::where('domain', $domain)->first();
            $domainId = $domainModel ? $domainModel->domain_id : null;
        }
        
        $result = (object) [
            'valid' => false,
            'dns_record_found' => false,
            'dns_record' => null,
            'dns_id' => null,
            'policy_found' => false,
            'mode' => null,
            'max_age' => null,
            'mx_record' => null,
            'error_message' => null,
        ];
        
        // Step 1: Check DNS TXT record for _mta-sts
        $txtRecords = $this->getDnsTxtRecords("_mta-sts.{$domain}");
        
        if (empty($txtRecords)) {
            $result->error_message = 'No MTA-STS DNS record found (_mta-sts.' . $domain . ' TXT)';
            $this->saveRecord($domain, $domainId, $result);
            return $result;
        }
        
        // Parse the DNS record
        foreach ($txtRecords as $record) {
            if (str_starts_with($record, 'v=STSv1')) {
                $result->dns_record_found = true;
                $result->dns_record = $record;
                
                // Parse DNS record parts
                $parts = [];
                $segments = explode(';', $record);
                
                foreach ($segments as $segment) {
                    $segment = trim($segment);
                    if (empty($segment)) continue;
                    
                    $kv = explode('=', $segment, 2);
                    if (count($kv) === 2) {
                        $key = trim($kv[0]);
                        $value = trim($kv[1]);
                        $parts[$key] = $value;
                    }
                }
                
                // DNS record should have an 'id' field
                if (isset($parts['id'])) {
                    $result->dns_id = $parts['id'];
                } else {
                    $result->error_message = 'MTA-STS DNS record missing required "id" field';
                    $this->saveRecord($domain, $domainId, $result);
                    return $result;
                }
                
                break;
            }
        }
        
        if (!$result->dns_record_found) {
            $result->error_message = 'No valid MTA-STS DNS record found (must start with v=STSv1)';
            $this->saveRecord($domain, $domainId, $result);
            return $result;
        }
        
        // Step 2: Fetch the policy file
        $policyFile = $this->fetchPolicyFile($domain);
        
        if (!$policyFile) {
            $result->error_message = 'MTA-STS policy file not found at https://mta-sts.' . $domain . '/.well-known/mta-sts.txt';
            $this->saveRecord($domain, $domainId, $result);
            return $result;
        }
        
        // Step 3: Parse policy file
        $result->policy_found = true;
        $result->mode = $policyFile['mode'] ?? null;
        $result->max_age = $policyFile['max_age'] ?? null;
        $result->mx_record = $policyFile['mx'] ?? null;
        
        // Validate policy file fields
        if (!$result->mode) {
            $result->error_message = 'Policy file missing "mode" field';
            $this->saveRecord($domain, $domainId, $result);
            return $result;
        }
        
        if (!in_array($result->mode, ['enforce', 'testing', 'none'])) {
            $result->error_message = "Invalid mode '{$result->mode}' - must be enforce, testing, or none";
            $this->saveRecord($domain, $domainId, $result);
            return $result;
        }
        
        if (!$result->max_age) {
            $result->error_message = 'Policy file missing "max_age" field';
            $this->saveRecord($domain, $domainId, $result);
            return $result;
        }
        
        if (!is_numeric($result->max_age) || (int)$result->max_age < 0) {
            $result->error_message = "Invalid max_age '{$result->max_age}' - must be a positive number";
            $this->saveRecord($domain, $domainId, $result);
            return $result;
        }
        
        $result->valid = true;
        $this->saveRecord($domain, $domainId, $result);
        
        return $result;
    }
    
    /**
     * Get DNS TXT records for a domain
     */
    protected function getDnsTxtRecords(string $domain): array
    {
        $records = [];
        
        try {
            $result = dns_get_record($domain, DNS_TXT);
            
            if ($result === false) {
                Log::warning("Failed to get TXT records for {$domain}");
                return [];
            }
            
            foreach ($result as $record) {
                if (isset($record['txt'])) {
                    $records[] = $record['txt'];
                }
            }
        } catch (\Exception $e) {
            Log::error("Error getting TXT records for {$domain}: " . $e->getMessage());
        }
        
        return $records;
    }
    
    /**
     * Get DNS MX records for a domain
     */
    protected function getDnsMxRecords(string $domain): array
    {
        $records = [];
        
        try {
            $result = dns_get_record($domain, DNS_MX);
            
            if ($result === false) {
                Log::warning("Failed to get MX records for {$domain}");
                return [];
            }
            
            foreach ($result as $record) {
                if (isset($record['target'])) {
                    $records[] = [
                        'target' => $record['target'],
                        'priority' => $record['pri'] ?? 10,
                    ];
                }
            }
            
            usort($records, function($a, $b) {
                return $a['priority'] <=> $b['priority'];
            });
            
        } catch (\Exception $e) {
            Log::error("Error getting MX records for {$domain}: " . $e->getMessage());
        }
        
        return $records;
    }
    
    /**
     * Fetch and parse the MTA-STS policy file
     */
    public function fetchPolicyFile(string $domain): ?array
    {
        $url = "https://mta-sts.{$domain}/.well-known/mta-sts.txt";
        
        try {
            $response = Http::timeout(10)->get($url);
            
            if (!$response->successful()) {
                Log::warning("Failed to fetch MTA-STS policy for {$domain}", [
                    'status' => $response->status()
                ]);
                return null;
            }
            
            $content = $response->body();
            $policy = [];
            
            foreach (explode("\n", $content) as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }
                
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    
                    if ($key === 'mx') {
                        $policy['mx'] = array_map('trim', explode(',', $value));
                    } else {
                        $policy[$key] = $value;
                    }
                }
            }
            
            // Validate required fields
            if (!isset($policy['version']) || $policy['version'] !== 'STSv1') {
                Log::warning("Invalid MTA-STS policy version for {$domain}", [
                    'version' => $policy['version'] ?? 'missing'
                ]);
                return null;
            }
            
            if (!isset($policy['mode']) || !in_array($policy['mode'], ['enforce', 'testing', 'none'])) {
                Log::warning("Invalid MTA-STS policy mode for {$domain}", [
                    'mode' => $policy['mode'] ?? 'missing'
                ]);
                return null;
            }
            
            if (!isset($policy['max_age']) || !is_numeric($policy['max_age'])) {
                Log::warning("Invalid MTA-STS policy max_age for {$domain}", [
                    'max_age' => $policy['max_age'] ?? 'missing'
                ]);
                return null;
            }
            
            return $policy;
            
        } catch (\Exception $e) {
            Log::error("Error fetching MTA-STS policy for {$domain}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Validate MX records against policy file
     */
    public function validateMxAgainstPolicy(string $domain, array $policy): array
    {
        $result = [
            'valid' => false,
            'dns_mx' => [],
            'policy_mx' => $policy['mx'] ?? [],
            'missing_in_policy' => [],
            'missing_in_dns' => [],
        ];
        
        // Get MX records from DNS
        $mxRecords = $this->getDnsMxRecords($domain);
        $result['dns_mx'] = array_map(function($record) {
            return $record['target'];
        }, $mxRecords);
        
        // Normalize domains (remove trailing dots)
        $dnsMx = array_map(function($mx) {
            return rtrim(strtolower($mx), '.');
        }, $result['dns_mx']);
        
        $policyMx = array_map(function($mx) {
            return rtrim(strtolower($mx), '.');
        }, $result['policy_mx']);
        
        // Check for mismatches
        $result['missing_in_policy'] = array_diff($dnsMx, $policyMx);
        $result['missing_in_dns'] = array_diff($policyMx, $dnsMx);
        
        $result['valid'] = empty($result['missing_in_policy']) && empty($result['missing_in_dns']);
        
        return $result;
    }
    
    /**
     * Check all domains from your Domain model
     */
    public function checkAllDomains(): int
    {
        $domains = Domain::where('enabled', true)->get();
        $count = 0;
        
        foreach ($domains as $domain) {
            $this->checkDomain($domain->domain, $domain->domain_id);
            $count++;
            
            if ($count % 10 === 0) {
                usleep(100000);
            }
        }
        
        return $count;
    }
    
    /**
     * Check only domains that need updating
     */
    public function checkDomainsNeedingUpdate(): int
    {
        try {
            $domainNames = MtaStsCheck::needsUpdate()->pluck('domain')->toArray();
            $allDomains = Domain::where('enabled', true)->pluck('domain')->toArray();
            $domainsWithoutRecord = array_diff($allDomains, MtaStsCheck::pluck('domain')->toArray());
            
            $domainsToCheck = array_merge($domainNames, $domainsWithoutRecord);
            $count = 0;
            
            foreach ($domainsToCheck as $domain) {
                $domainModel = Domain::where('domain', $domain)->first();
                if ($domainModel) {
                    $this->checkDomain($domain, $domainModel->domain_id);
                    $count++;
                }
                
                if ($count % 10 === 0) {
                    usleep(100000);
                }
            }
            
            return $count;
            
        } catch (\Exception $e) {
            $checkedDomains = MtaStsCheck::where('checked_at', '>', now()->subDays(7))
                ->pluck('domain')
                ->toArray();
                
            $domains = Domain::where('enabled', true)
                ->whereNotIn('domain', $checkedDomains)
                ->get();
                
            $count = 0;
            
            foreach ($domains as $domain) {
                $this->checkDomain($domain->domain, $domain->domain_id);
                $count++;
                
                if ($count % 10 === 0) {
                    usleep(100000);
                }
            }
            
            return $count;
        }
    }
    
    /**
     * Get statistics about MTA-STS implementation
     */
    public function getStats(): array
    {
        $total = MtaStsCheck::count();
        $totalDomains = Domain::where('enabled', true)->count();
        
        $stats = [
            'total' => $total,
            'total_domains' => $totalDomains,
            'coverage' => $totalDomains > 0 ? round(($total / $totalDomains) * 100, 2) : 0,
            'dns_valid' => MtaStsCheck::validDns()->count(),
            'dns_no_record' => MtaStsCheck::where('dns_valid', false)
                ->whereNotNull('checked_at')
                ->count(),
            'dns_invalid' => MtaStsCheck::where('dns_valid', false)
                ->whereNotNull('error_message')
                ->count(),
            'policy_valid_count' => MtaStsCheck::validPolicy()->count(),
            'policy_invalid_count' => MtaStsCheck::where('policy_valid', false)
                ->whereNotNull('policy_fetched_at')
                ->count(),
            'modes' => [],
            'max_age_distribution' => [],
            'expired_count' => MtaStsCheck::expired()->count(),
            'expired_domains' => MtaStsCheck::expired()->pluck('domain')->toArray(),
            'mx_mismatch_count' => MtaStsCheck::withMismatch()->count(),
            'mx_mismatch_domains' => MtaStsCheck::withMismatch()->pluck('domain')->toArray(),
        ];
        
        // Get mode breakdown
        $modes = MtaStsCheck::validPolicy()
            ->select('dns_mode')
            ->selectRaw('count(*) as count')
            ->groupBy('dns_mode')
            ->get();
            
        foreach ($modes as $mode) {
            $stats['modes'][$mode->dns_mode ?? 'unknown'] = $mode->count;
        }
        
        // Get max age distribution
        $maxAges = MtaStsCheck::validPolicy()
            ->select('dns_max_age')
            ->selectRaw('count(*) as count')
            ->groupBy('dns_max_age')
            ->get();
            
        foreach ($maxAges as $maxAge) {
            $key = $maxAge->dns_max_age ? $maxAge->dns_max_age . 's' : 'not set';
            $stats['max_age_distribution'][$key] = $maxAge->count;
        }
        
        return $stats;
    }
    
    /**
     * Save or update the MTA-STS record
     */
    protected function saveRecord(string $domain, ?int $domainId, object $result): void
    {
        $record = MtaStsCheck::firstOrNew(['domain' => $domain]);
        
        $record->domain_id = $domainId ?? $record->domain_id;
        $record->checked_at = now();
        $record->next_check_at = now()->addDays(7);
        
        // DNS record data
        $record->dns_valid = $result->dns_record_found && $result->valid;
        $record->dns_policy = $result->dns_record;
        
        // Policy file data (these come from the policy file, not DNS)
        $record->dns_mode = $result->mode;  // From policy file
        $record->dns_max_age = $result->max_age;  // From policy file
        $record->dns_mx = $result->mx_record ? implode(',', (array)$result->mx_record) : null;  // From policy file
        
        // Store DNS ID separately
        if ($result->dns_id) {
            $record->raw_data = array_merge(
                $record->raw_data ?? [],
                ['dns_id' => $result->dns_id]
            );
        }
        
        // Policy file validation
        $record->policy_valid = $result->policy_found && $result->valid;
        if ($result->policy_found) {
            $record->policy_fetched_at = now();
            $record->policy_data = [
                'mode' => $result->mode,
                'max_age' => $result->max_age,
                'mx' => $result->mx_record,
            ];
        }
        
        // Calculate expiry based on max_age from policy file
        if ($result->max_age && is_numeric($result->max_age) && $result->valid) {
            $record->dns_expires_at = now()->addSeconds((int)$result->max_age);
        } else {
            $record->dns_expires_at = null;
        }
        
        $record->error_message = $result->error_message;
        
        // If we have a policy file and MX records, validate them
        if ($result->policy_found && $result->valid && $result->mx_record) {
            $policy = [
                'version' => 'STSv1',
                'mode' => $result->mode,
                'max_age' => $result->max_age,
                'mx' => (array)$result->mx_record,
            ];
            $mxCheck = $this->validateMxAgainstPolicy($domain, $policy);
            $record->mx_mismatch = !$mxCheck['valid'];
            $record->mx_validation_details = $mxCheck;
        } else {
            $record->mx_mismatch = false;
            $record->mx_validation_details = null;
        }
        
        $record->save();
    }
}