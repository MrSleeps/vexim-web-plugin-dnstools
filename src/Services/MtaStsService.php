<?php

namespace VEximweb\Plugin\DnsTools\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use VEximweb\Plugin\DnsTools\Models\MtaStsCheck;
use VEximweb\Core\Data\Models\Domain;
use VEximweb\Plugin\MTASTS\Models\MtaSts;
use VEximweb\Core\Data\Repositories\Interfaces\SettingRepositoryInterface;

class MtaStsService
{
    /**
     * @var SettingRepositoryInterface
     */
    protected SettingRepositoryInterface $settingsRepository;

    /**
     * MtaStsService constructor.
     *
     * @param SettingRepositoryInterface $settingsRepository
     */
    public function __construct(SettingRepositoryInterface $settingsRepository)
    {
        $this->settingsRepository = $settingsRepository;
    }

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
            'dns_ttl' => null,
            'policy_found' => false,
            'mode' => null,
            'max_age' => null,
            'mx_record' => null,
            'error_message' => null,
            'cname_found' => false,
            'cname_target' => null,
            'warning' => null,
        ];

        // Step 1: Check DNS TXT record for _mta-sts
        $dnsResult = $this->getDnsTxtRecords("_mta-sts.{$domain}");

        if (empty($dnsResult)) {
            $result->error_message = 'No MTA-STS DNS record found (_mta-sts.' . $domain . ' TXT)';
            $this->saveRecord($domain, $domainId, $result);
            return $result;
        }

        // Parse the DNS record
        $foundValidDns = false;
        foreach ($dnsResult as $dnsRecord) {
            $txt = $dnsRecord['txt'];
            if (str_starts_with($txt, 'v=STSv1')) {
                $result->dns_record_found = true;
                $result->dns_record = $txt;
                $result->dns_ttl = $dnsRecord['ttl'] ?? 300;

                // Parse DNS record parts
                $parts = $this->parseDnsRecord($txt);

                // Validate DNS record
                $validation = $this->validateDnsRecord($parts);
                if (!$validation['valid']) {
                    $result->error_message = $validation['error'];
                    $this->saveRecord($domain, $domainId, $result);
                    return $result;
                }

                $result->dns_id = $parts['id'];
                $foundValidDns = true;
                break;
            }
        }

        if (!$result->dns_record_found) {
            $result->error_message = 'No valid MTA-STS DNS record found (must start with v=STSv1)';
            $this->saveRecord($domain, $domainId, $result);
            return $result;
        }

        // Step 1.5: Check if CNAME exists (READ-ONLY - just check)
        $cnameRecord = $this->getDnsCnameRecord("mta-sts.{$domain}");
        if ($cnameRecord) {
            $result->cname_found = true;
            $result->cname_target = $cnameRecord['target'];
            Log::debug('MTA-STS CNAME found during check', [
                'domain' => $domain,
                'target' => $cnameRecord['target']
            ]);
        } else {
            Log::debug('MTA-STS CNAME not found during check', [
                'domain' => $domain
            ]);
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

        // Step 4: Compare DNS ID with policy ID if present
        if (isset($policyFile['id']) && $result->dns_id !== $policyFile['id']) {
            Log::warning("MTA-STS DNS ID mismatch for {$domain}", [
                'dns_id' => $result->dns_id,
                'policy_id' => $policyFile['id']
            ]);
            // Still valid, but log the warning
            $result->warning = "DNS ID ({$result->dns_id}) does not match policy ID ({$policyFile['id']})";
        }

        $result->valid = true;
        $this->saveRecord($domain, $domainId, $result);

        return $result;
    }

    /**
     * Dispatch event to create/update CNAME record for mta-sts.domain
     *
     * @param string $domain
     * @param int|null $domainId
     * @return array
     */
    protected function dispatchCnameCreationEvent(string $domain, ?int $domainId = null): array
    {
        $result = [
            'created' => false,
            'target' => null,
            'error' => null
        ];
        
        try {
            // Get the default CNAME target from settings
            $defaultTarget = $this->settingsRepository->get('mta_sts_cname_default', '');
            
            Log::debug('MTA-STS CNAME settings check', [
                'domain' => $domain,
                'default_target' => $defaultTarget,
                'setting_exists' => $this->settingsRepository->has('mta_sts_cname_default')
            ]);
            
            if (empty($defaultTarget)) {
                $result['error'] = 'Default MTA-STS CNAME target not configured in settings';
                Log::error('MTA-STS CNAME creation failed: default target not configured', [
                    'domain' => $domain,
                    'setting_key' => 'mta_sts_cname_default'
                ]);
                return $result;
            }
            
            $cnameDomain = "mta-sts.{$domain}";
            $result['target'] = $defaultTarget;
            
            // Check if the event class exists
            if (!class_exists(\App\Events\MtaStsRecordGenerated::class)) {
                $result['error'] = 'MtaStsRecordGenerated event class not found';
                Log::warning('MTA-STS CNAME creation skipped: Event class not found');
                return $result;
            }
            
            // Check if CNAME already exists before creating
            $existingCname = $this->getDnsCnameRecord($cnameDomain);
            
            if ($existingCname && $existingCname['target'] === $defaultTarget) {
                // CNAME already exists with correct target
                $result['created'] = true;
                Log::info('MTA-STS CNAME already exists with correct target', [
                    'domain' => $cnameDomain,
                    'target' => $defaultTarget
                ]);
                return $result;
            }
            
            // Get the domain model for the event
            $domainModel = Domain::where('domain', $domain)->first();
            if (!$domainModel) {
                $result['error'] = 'Domain not found';
                Log::error('Domain not found for MTA-STS CNAME', ['domain' => $domain]);
                return $result;
            }
            
            // Dispatch event to create/update the CNAME
            Log::info('Dispatching MtaStsRecordGenerated event for CNAME', [
                'zone' => $domain,
                'name' => 'mta-sts',
                'type' => 'CNAME',
                'content' => $defaultTarget,
                'is_update' => $existingCname ? true : false,
                'domain_id' => $domainModel->domain_id
            ]);
            
            // Dispatch the event with the new flexible constructor
            // Note: We're passing null for MtaSts as this is a CNAME record
            Event::dispatch(new \App\Events\MtaStsRecordGenerated(
                mtaSts: null,  // No MTA-STS record for CNAME
                zone: $domain,
                name: 'mta-sts',
                type: 'CNAME',
                content: $defaultTarget,
                ttl: 3600,
                operation: $existingCname ? 'update' : 'create'
            ));
            
            // Log that we dispatched the event
            Log::info('MTA-STS CNAME creation event dispatched successfully', [
                'domain' => $cnameDomain,
                'target' => $defaultTarget,
                'event_class' => \App\Events\MtaStsRecordGenerated::class
            ]);
            
            // Note: We can't know if the event was successfully processed here
            // The event listener will handle success/failure and notifications
            $result['created'] = true; // Assume success, the listener handles errors
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            Log::error('Error dispatching MTA-STS CNAME creation event', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return $result;
    }
    
    /**
     * Get DNS CNAME record for a domain
     *
     * @param string $domain
     * @return array|null
     */
    protected function getDnsCnameRecord(string $domain): ?array
    {
        try {
            $result = dns_get_record($domain, DNS_CNAME);
            
            if ($result === false || empty($result)) {
                Log::debug('No CNAME record found', ['domain' => $domain]);
                return null;
            }
            
            // Return the first CNAME record found
            foreach ($result as $record) {
                if (isset($record['target'])) {
                    Log::debug('Found CNAME record', [
                        'domain' => $domain,
                        'target' => $record['target'],
                        'ttl' => $record['ttl'] ?? 300
                    ]);
                    return [
                        'target' => $record['target'],
                        'ttl' => $record['ttl'] ?? 300,
                        'host' => $record['host'] ?? $domain,
                    ];
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("Error getting CNAME record for {$domain}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Parse DNS record into parts
     */
    protected function parseDnsRecord(string $record): array
    {
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
        
        return $parts;
    }
    
    /**
     * Validate DNS record format
     */
    protected function validateDnsRecord(array $parts): array
    {
        // Check version
        if (!isset($parts['v']) || $parts['v'] !== 'STSv1') {
            return [
                'valid' => false,
                'error' => 'Missing or invalid version (must be v=STSv1)'
            ];
        }
        
        // Check ID
        if (!isset($parts['id']) || empty($parts['id'])) {
            return [
                'valid' => false,
                'error' => 'Missing required "id" field'
            ];
        }
        
        // Validate ID format (alphanumeric, dash, underscore)
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $parts['id'])) {
            return [
                'valid' => false,
                'error' => 'Invalid "id" format - use alphanumeric, dash, or underscore only'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Get DNS TXT records for a domain with TTL
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
                    $records[] = [
                        'txt' => $record['txt'],
                        'ttl' => $record['ttl'] ?? 300,
                    ];
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
     * Validate MX records against policy file with wildcard support
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
            return rtrim(strtolower($record['target']), '.');
        }, $mxRecords);
        
        // Normalize policy MX records
        $policyMx = array_map(function($mx) {
            return rtrim(strtolower($mx), '.');
        }, $result['policy_mx']);
        
        // Check for matches with wildcard support
        $matchedDns = [];
        $matchedPolicy = [];
        
        foreach ($result['dns_mx'] as $dnsMx) {
            foreach ($policyMx as $policyMxPattern) {
                if ($this->mxMatchesPattern($dnsMx, $policyMxPattern)) {
                    $matchedDns[] = $dnsMx;
                    $matchedPolicy[] = $policyMxPattern;
                    break;
                }
            }
        }
        
        // Find missing records
        $result['missing_in_policy'] = array_diff($result['dns_mx'], $matchedDns);
        $result['missing_in_dns'] = array_diff($policyMx, $matchedPolicy);
        
        $result['valid'] = empty($result['missing_in_policy']) && empty($result['missing_in_dns']);
        
        return $result;
    }
    
    /**
     * Check if an MX record matches a pattern (supports wildcards)
     */
    protected function mxMatchesPattern(string $mx, string $pattern): bool
    {
        // Exact match
        if ($mx === $pattern) {
            return true;
        }
        
        // Wildcard pattern matching
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
            return preg_match($regex, $mx) === 1;
        }
        
        // Check if MX is a subdomain of the pattern
        if (str_starts_with($pattern, '.')) {
            return str_ends_with($mx, $pattern);
        }
        
        return false;
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
            
            // Rate limiting to avoid DNS throttling
            if ($count % 10 === 0) {
                usleep(100000); // 100ms delay
            }
        }
        
        return $count;
    }
    
    /**
     * Check only domains that need updating
     * This will check domains with no record AND domains with expired/outdated records
     */
    public function checkDomainsNeedingUpdate(): int
    {
        try {
            // Get all enabled domains
            $allDomains = Domain::where('enabled', true)->pluck('domain')->toArray();
            
            // Get domains that already have a check record
            $existingDomains = MtaStsCheck::pluck('domain')->toArray();
            
            // Find domains without any check record (these need checking)
            $domainsWithoutRecord = array_diff($allDomains, $existingDomains);
            
            // Get domains that have a check record but need updating (expired or next_check_at <= now)
            $domainsNeedingUpdate = MtaStsCheck::needsUpdate()->pluck('domain')->toArray();
            
            // Merge both lists
            $domainsToCheck = array_unique(array_merge($domainsWithoutRecord, $domainsNeedingUpdate));
            
            Log::info('MTA-STS domains to check', [
                'all_domains' => count($allDomains),
                'existing_records' => count($existingDomains),
                'without_record' => count($domainsWithoutRecord),
                'needing_update' => count($domainsNeedingUpdate),
                'total_to_check' => count($domainsToCheck)
            ]);
            
            $count = 0;
            
            foreach ($domainsToCheck as $domain) {
                $domainModel = Domain::where('domain', $domain)->first();
                if ($domainModel) {
                    $this->checkDomain($domain, $domainModel->domain_id);
                    $count++;
                }
                
                // Rate limiting to avoid DNS throttling
                if ($count % 10 === 0) {
                    usleep(100000); // 100ms delay
                }
            }
            
            return $count;
            
        } catch (\Exception $e) {
            Log::error("Error in checkDomainsNeedingUpdate: " . $e->getMessage());
            
            // Fallback: check ALL enabled domains if there's an error
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
            'valid' => MtaStsCheck::validDns()->count(),
            'no_record' => MtaStsCheck::where('dns_valid', false)
                ->whereNotNull('checked_at')
                ->count(),
            'invalid' => MtaStsCheck::where('dns_valid', false)
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
     * This method properly updates the database with all check results
     */
    protected function saveRecord(string $domain, ?int $domainId, object $result): void
    {
        try {
            // Convert mx_record to string if it's an array
            $mxRecord = $result->mx_record;
            if (is_array($mxRecord)) {
                $mxRecord = implode(',', $mxRecord);
            }

            // Prepare data for update or create
            $data = [
                'domain' => $domain,
                'domain_id' => $domainId,
                'checked_at' => now(),
                'next_check_at' => now()->addHours(24),
                'dns_valid' => $result->dns_record_found && $result->valid,
                'dns_policy' => $result->dns_record,
                'dns_mode' => $result->mode,
                'dns_mx' => $mxRecord,
                'dns_max_age' => $result->max_age,
                'error_message' => $result->error_message,
                'raw_data' => [
                    'dns_id' => $result->dns_id,
                    'dns_ttl' => $result->dns_ttl,
                    'policy_found' => $result->policy_found,
                    'cname_found' => $result->cname_found,
                    'cname_target' => $result->cname_target,
                    'warning' => $result->warning,
                    'cname_checked_at' => now()->toDateTimeString(),
                ],
            ];

            // Set expiry date if max_age is set and valid
            if ($result->max_age && is_numeric($result->max_age) && $result->valid) {
                $data['dns_expires_at'] = now()->addSeconds((int)$result->max_age);
            } else {
                $data['dns_expires_at'] = null;
            }

            // Set policy data if policy was found
            if ($result->policy_found) {
                $data['policy_valid'] = $result->valid;
                $data['policy_fetched_at'] = now();
                $data['policy_data'] = [
                    'mode' => $result->mode,
                    'max_age' => $result->max_age,
                    'mx' => $result->mx_record,
                ];
            } else {
                $data['policy_valid'] = false;
                $data['policy_data'] = null;
            }

            // If we have a policy and it's valid, validate MX records
            if ($result->policy_found && $result->valid && $result->mx_record) {
                $policy = [
                    'version' => 'STSv1',
                    'mode' => $result->mode,
                    'max_age' => $result->max_age,
                    'mx' => (array)$result->mx_record,
                ];
                $mxCheck = $this->validateMxAgainstPolicy($domain, $policy);
                $data['mx_mismatch'] = !$mxCheck['valid'];
                $data['mx_validation_details'] = $mxCheck;
            } else {
                $data['mx_mismatch'] = false;
                $data['mx_validation_details'] = null;
            }

            // Update or create the record
            $record = MtaStsCheck::updateOrCreate(
                ['domain' => $domain],
                $data
            );

            Log::info('MTA-STS check saved to database', [
                'domain' => $domain,
                'record_id' => $record->id,
                'valid' => $result->valid,
                'mode' => $result->mode,
                'dns_record_found' => $result->dns_record_found,
                'policy_found' => $result->policy_found,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save MTA-STS check record', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw the exception to let the caller know saving failed
            throw $e;
        }
    }
    
    /**
     * Create or update an MTA-STS record in the database
     */
    public function createOrUpdateMtaStsRecord(int $domainId, string $policyType, int $maxAge, string $generatedId, ?array $extraData = null): MtaSts
    {
        // Check if record exists in the MTASTS model
        $record = MtaSts::where('domain_id', $domainId)->first();
        
        if ($record) {
            // Update existing record
            $record->policy_type = $policyType;
            $record->max_age = $maxAge;
            $record->generated_id = $generatedId;
            
            // Update any extra fields if provided
            if ($extraData) {
                foreach ($extraData as $key => $value) {
                    if (in_array($key, ['dns_record_name', 'dns_record_value', 'dns_ttl', 'update_dns'])) {
                        $record->$key = $value;
                    }
                }
            }
            
            $record->save();
            
            Log::info('MTA-STS record updated', [
                'domain_id' => $domainId,
                'policy_type' => $policyType,
                'record_id' => $record->id
            ]);
            
            return $record;
        }
        
        // Create new record
        $record = MtaSts::create([
            'domain_id' => $domainId,
            'policy_type' => $policyType,
            'max_age' => $maxAge,
            'generated_id' => $generatedId,
        ]);
        
        Log::info('MTA-STS record created', [
            'domain_id' => $domainId,
            'policy_type' => $policyType,
            'record_id' => $record->id
        ]);
        
        return $record;
    }
    
    /**
     * Create MTA-STS CNAME record for a domain
     * This is a WRITE operation - creates the CNAME record
     */
    public function createMtaStsCname(string $domain, ?int $domainId = null): array
    {
        $result = [
            'created' => false,
            'target' => null,
            'error' => null
        ];

        try {
            // Get the default CNAME target from settings
            $defaultTarget = $this->settingsRepository->get('mta_sts_cname_default', '');

            if (empty($defaultTarget)) {
                $result['error'] = 'Default MTA-STS CNAME target not configured in settings';
                Log::error('MTA-STS CNAME creation failed: default target not configured', [
                    'domain' => $domain,
                    'setting_key' => 'mta_sts_cname_default'
                ]);
                return $result;
            }

            $result['target'] = $defaultTarget;

            // Check if the event class exists
            if (!class_exists(\App\Events\MtaStsRecordGenerated::class)) {
                $result['error'] = 'MtaStsRecordGenerated event class not found';
                Log::warning('MTA-STS CNAME creation skipped: Event class not found');
                return $result;
            }

            // Dispatch event to create the CNAME
            Log::info('Creating MTA-STS CNAME via event', [
                'zone' => $domain,
                'name' => 'mta-sts',
                'type' => 'CNAME',
                'content' => $defaultTarget
            ]);

            Event::dispatch(new \App\Events\MtaStsRecordGenerated(
                mtaSts: null,
                zone: $domain,
                name: 'mta-sts',
                type: 'CNAME',
                content: $defaultTarget,
                ttl: 3600,
                operation: 'create'
            ));

            $result['created'] = true;

            Log::info('MTA-STS CNAME creation event dispatched', [
                'domain' => $domain,
                'target' => $defaultTarget
            ]);

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            Log::error('Error creating MTA-STS CNAME', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }    
    
    /**
     * Get a specific MTA-STS record by domain
     */
    public function getRecord(string $domain): ?MtaStsCheck
    {
        return MtaStsCheck::where('domain', $domain)->first();
    }
    
    /**
     * Get all MTA-STS records
     */
    public function getAllRecords(): \Illuminate\Database\Eloquent\Collection
    {
        return MtaStsCheck::all();
    }
    
    /**
     * Set the settings repository (for testing or dynamic injection)
     *
     * @param SettingRepositoryInterface $settingsRepository
     * @return self
     */
    public function setSettingsRepository(SettingRepositoryInterface $settingsRepository): self
    {
        $this->settingsRepository = $settingsRepository;
        return $this;
    }
}