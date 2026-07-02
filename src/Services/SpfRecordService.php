<?php

namespace VEximweb\Plugin\DnsTools\Services;

use SPFLib\Record;
use SPFLib\Term\Mechanism;
use SPFLib\Term\Modifier;
use SPFLib\SemanticValidator;
use SPFLib\Decoder;
use SPFLib\Exception;
use SPFLib\Exception\InvalidTermException;
use VEximweb\Plugin\DnsTools\Models\SystemDomains as Domain;
use VEximweb\Plugin\DnsTools\Models\SpfCheck;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use IPLib\Factory;
use IPLib\Address\IPv4;
use IPLib\Address\IPv6;

class SpfRecordService
{
    protected $decoder;
    
    public function __construct()
    {
        $this->decoder = new Decoder();
    }
    
    /**
     * Generate an SPF record from form data
     *
     * @param Domain $domain
     * @param array $data
     * @return array
     */
    public function generate(Domain $domain, array $data): array
    {
        try {
            // Initialize the SPF record with the domain
            $record = new Record($domain->domain);
            
            // 1. Add Include mechanisms (sending services)
            if (!empty($data['spf_includes'])) {
                foreach ($data['spf_includes'] as $include) {
                    if (!empty($include['service'])) {
                        $record->addTerm(
                            new Mechanism\IncludeMechanism(
                                Mechanism::QUALIFIER_PASS,
                                $include['service']
                            )
                        );
                    }
                }
            }
            
            // 2. Add IPv4 addresses
            if (!empty($data['spf_ipv4'])) {
                foreach ($data['spf_ipv4'] as $ipv4) {
                    if (!empty($ipv4['ipv4'])) {
                        $ip = $this->parseIpAddress($ipv4['ipv4']);
                        if ($ip instanceof IPv4) {
                            $record->addTerm(
                                new Mechanism\Ip4Mechanism(
                                    Mechanism::QUALIFIER_PASS,
                                    $ip
                                )
                            );
                        } else {
                            Log::warning('Invalid IPv4 address: ' . $ipv4['ipv4']);
                        }
                    }
                }
            }
            
            // 3. Add IPv6 addresses
            if (!empty($data['spf_ipv6'])) {
                foreach ($data['spf_ipv6'] as $ipv6) {
                    if (!empty($ipv6['ipv6'])) {
                        $ip = $this->parseIpAddress($ipv6['ipv6']);
                        if ($ip instanceof IPv6) {
                            $record->addTerm(
                                new Mechanism\Ip6Mechanism(
                                    Mechanism::QUALIFIER_PASS,
                                    $ip
                                )
                            );
                        } else {
                            Log::warning('Invalid IPv6 address: ' . $ipv6['ipv6']);
                        }
                    }
                }
            }
            
            // 4. Add MX mechanism
            if (!empty($data['spf_use_mx'])) {
                if (!empty($data['spf_mx_domain'])) {
                    $record->addTerm(
                        new Mechanism\MxMechanism(
                            Mechanism::QUALIFIER_PASS,
                            $data['spf_mx_domain']
                        )
                    );
                } else {
                    $record->addTerm(
                        new Mechanism\MxMechanism(
                            Mechanism::QUALIFIER_PASS
                        )
                    );
                }
            }
            
            // 5. Add A mechanism
            if (!empty($data['spf_use_a'])) {
                if (!empty($data['spf_a_domain'])) {
                    $record->addTerm(
                        new Mechanism\AMechanism(
                            Mechanism::QUALIFIER_PASS,
                            $data['spf_a_domain']
                        )
                    );
                } else {
                    $record->addTerm(
                        new Mechanism\AMechanism(
                            Mechanism::QUALIFIER_PASS
                        )
                    );
                }
            }
            
            // 6. Add Advanced mechanisms
            if (!empty($data['spf_ptr']) && $data['spf_ptr'] === 'ptr') {
                $record->addTerm(
                    new Mechanism\PtrMechanism(
                        Mechanism::QUALIFIER_PASS
                    )
                );
            }
            
            if (!empty($data['spf_exists'])) {
                $record->addTerm(
                    new Mechanism\ExistsMechanism(
                        Mechanism::QUALIFIER_PASS,
                        $data['spf_exists']
                    )
                );
            }
            
            // 7. Add Redirect modifier
            if (!empty($data['spf_redirect'])) {
                try {
                    $record->addTerm(
                        new Modifier\RedirectModifier($data['spf_redirect'])
                    );
                } catch (InvalidTermException $e) {
                    Log::warning('Invalid redirect modifier: ' . $e->getMessage());
                }
            }
            
            // 8. Add Exp modifier
            if (!empty($data['spf_exp'])) {
                try {
                    $record->addTerm(
                        new Modifier\ExpModifier($data['spf_exp'])
                    );
                } catch (InvalidTermException $e) {
                    Log::warning('Invalid exp modifier: ' . $e->getMessage());
                }
            }
            
            // 9. Add the fail policy (ALL mechanism - MUST be last)
            $qualifier = $this->getQualifier($data['spf_fail_policy'] ?? '-all');
            $record->addTerm(new Mechanism\AllMechanism($qualifier));
            
            // Convert the record to string
            $spfString = (string) $record;
            
            // Validate the record and get issues
            $issues = $this->validateRecord($record);
            $lookupCount = $this->countLookups($record);
            
            if (!empty($issues)) {
                Log::warning('SPF record validation issues:', [
                    'domain' => $domain->domain,
                    'issues' => $issues
                ]);
            }
            
            return [
                'record' => $spfString,
                'lookups' => $lookupCount,
                'issues' => $issues,
                'is_valid' => empty($issues),
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to generate SPF record: ' . $e->getMessage(), [
                'domain' => $domain->domain,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'record' => 'v=spf1 -all',
                'lookups' => 0,
                'issues' => ['Error: ' . $e->getMessage()],
                'is_valid' => false,
            ];
        }
    }
    
    /**
     * Parse an IP address string with proper CIDR handling
     *
     * @param string $ipString
     * @return IPv4|IPv6|null
     */
    protected function parseIpAddress(string $ipString)
    {
        // Remove any whitespace
        $ipString = trim($ipString);
        
        try {
            return Factory::parseAddressString($ipString);
        } catch (\Exception $e) {
            Log::warning('Failed to parse IP address: ' . $ipString, ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Get the qualifier for the ALL mechanism
     *
     * @param string $policy
     * @return string
     */
    protected function getQualifier(string $policy): string
    {
        return match($policy) {
            '-all' => Mechanism::QUALIFIER_FAIL,
            '~all' => Mechanism::QUALIFIER_SOFTFAIL,
            '?all' => Mechanism::QUALIFIER_NEUTRAL,
            '+all' => Mechanism::QUALIFIER_PASS,
            default => Mechanism::QUALIFIER_FAIL,
        };
    }
    
    /**
     * Validate the SPF record and return any issues
     *
     * @param Record $record
     * @return array
     */
    protected function validateRecord(Record $record): array
    {
        try {
            $validator = new SemanticValidator();
            $issues = $validator->validate($record);
            
            $formattedIssues = [];
            foreach ($issues as $issue) {
                $formattedIssues[] = [
                    'level' => $issue->getLevel(),
                    'message' => $issue->getMessage(),
                    'term' => $issue->getTerm() ? (string) $issue->getTerm() : null,
                ];
            }
            
            return $formattedIssues;
        } catch (\Exception $e) {
            return [
                [
                    'level' => 'error',
                    'message' => 'Validation failed: ' . $e->getMessage(),
                    'term' => null,
                ]
            ];
        }
    }
    
    /**
     * Count the number of DNS lookups in the SPF record
     *
     * @param Record $record
     * @return int
     */
    protected function countLookups(Record $record): int
    {
        $lookupCount = 0;
        $terms = $record->getTerms();
        
        foreach ($terms as $term) {
            if ($term instanceof Mechanism\IncludeMechanism ||
                $term instanceof Mechanism\MxMechanism ||
                $term instanceof Mechanism\AMechanism ||
                $term instanceof Mechanism\PtrMechanism ||
                $term instanceof Mechanism\ExistsMechanism) {
                $lookupCount++;
            }
            
            if ($term instanceof Modifier\RedirectModifier) {
                $lookupCount++;
            }
        }
        
        return $lookupCount;
    }
    
    /**
     * Get a human-readable description of the SPF record
     *
     * @param string $recordString
     * @return array
     */
    public function describe(string $recordString): array
    {
        try {
            $record = $this->decoder->getRecordFromTXT($recordString);
            
            if ($record === null) {
                return ['error' => 'Not a valid SPF record'];
            }
            
            $terms = $record->getTerms();
            
            $description = [
                'version' => 'v=spf1',
                'mechanisms' => [],
                'modifiers' => [],
                'summary' => '',
            ];
            
            foreach ($terms as $term) {
                $termString = (string) $term;
                $mechanismName = $term->getName();
                
                if ($mechanismName === 'all') {
                    $description['mechanisms'][] = [
                        'type' => 'all',
                        'value' => $termString,
                        'description' => $this->getMechanismDescription($term),
                    ];
                } elseif ($mechanismName === 'include') {
                    $domain = $this->getDomainFromMechanism($term, $termString);
                    $description['mechanisms'][] = [
                        'type' => 'include',
                        'value' => $termString,
                        'domain' => $domain,
                        'description' => 'Include SPF records from ' . $domain,
                    ];
                } elseif ($mechanismName === 'ip4') {
                    $ip = $this->extractDomainFromString($termString);
                    $description['mechanisms'][] = [
                        'type' => 'ip4',
                        'value' => $termString,
                        'ip' => $ip,
                        'description' => 'Allow IP ' . $ip,
                    ];
                } elseif ($mechanismName === 'ip6') {
                    $ip = $this->extractDomainFromString($termString);
                    $description['mechanisms'][] = [
                        'type' => 'ip6',
                        'value' => $termString,
                        'ip' => $ip,
                        'description' => 'Allow IP ' . $ip,
                    ];
                } elseif ($mechanismName === 'mx') {
                    $domain = $this->getDomainFromMechanism($term, $termString) ?: 'current domain';
                    $description['mechanisms'][] = [
                        'type' => 'mx',
                        'value' => $termString,
                        'domain' => $domain,
                        'description' => 'Allow MX servers for ' . $domain,
                    ];
                } elseif ($mechanismName === 'a') {
                    $domain = $this->getDomainFromMechanism($term, $termString) ?: 'current domain';
                    $description['mechanisms'][] = [
                        'type' => 'a',
                        'value' => $termString,
                        'domain' => $domain,
                        'description' => 'Allow A record for ' . $domain,
                    ];
                } elseif ($mechanismName === 'ptr') {
                    $description['mechanisms'][] = [
                        'type' => 'ptr',
                        'value' => $termString,
                        'description' => 'PTR mechanism (deprecated)',
                    ];
                } elseif ($mechanismName === 'exists') {
                    $domain = $this->getDomainFromMechanism($term, $termString);
                    $description['mechanisms'][] = [
                        'type' => 'exists',
                        'value' => $termString,
                        'domain' => $domain,
                        'description' => 'Exists mechanism for ' . $domain,
                    ];
                } elseif ($mechanismName === 'redirect') {
                    $domain = $this->getDomainFromMechanism($term, $termString);
                    $description['modifiers'][] = [
                        'type' => 'redirect',
                        'value' => $termString,
                        'domain' => $domain,
                        'description' => 'Redirect to ' . $domain,
                    ];
                } elseif ($mechanismName === 'exp') {
                    $domain = $this->getDomainFromMechanism($term, $termString);
                    $description['modifiers'][] = [
                        'type' => 'exp',
                        'value' => $termString,
                        'domain' => $domain,
                        'description' => 'Explanation: ' . $domain,
                    ];
                } else {
                    $description['mechanisms'][] = [
                        'type' => $mechanismName,
                        'value' => $termString,
                        'description' => 'Unknown mechanism',
                    ];
                }
            }
            
            $parts = [];
            foreach ($description['mechanisms'] as $mech) {
                if ($mech['type'] !== 'all') {
                    $parts[] = $mech['value'];
                }
            }
            $description['summary'] = implode(' ', $parts);
            
            return $description;
            
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to parse SPF record: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Get a description for a mechanism
     *
     * @param Mechanism\AllMechanism $mechanism
     * @return string
     */
    protected function getMechanismDescription(Mechanism\AllMechanism $mechanism): string
    {
        $qualifier = $mechanism->getQualifier();
        return match($qualifier) {
            Mechanism::QUALIFIER_FAIL => 'Reject all other senders (Hard Fail)',
            Mechanism::QUALIFIER_SOFTFAIL => 'Accept but mark as suspicious (Soft Fail)',
            Mechanism::QUALIFIER_NEUTRAL => 'Do nothing (Neutral)',
            Mechanism::QUALIFIER_PASS => 'Allow all senders (DANGEROUS)',
            default => 'Unknown qualifier',
        };
    }
    
    /**
     * Flatten a complex SPF record to its simplest form
     *
     * @param string $recordString
     * @return array
     */
    public function flattenRecord(string $recordString): array
    {
        try {
            $record = $this->decoder->getRecordFromTXT($recordString);
            
            if ($record === null) {
                return ['error' => 'Not a valid SPF record'];
            }
            
            $terms = $record->getTerms();
            
            $ip4List = [];
            $ip6List = [];
            $includes = [];
            $otherMechanisms = [];
            $allQualifier = null;
            
            foreach ($terms as $term) {
                $termString = (string) $term;
                $mechanismName = $term->getName();
                
                if ($mechanismName === 'ip4') {
                    $ip = $this->extractDomainFromString($termString);
                    $ip4List[] = $ip;
                } elseif ($mechanismName === 'ip6') {
                    $ip = $this->extractDomainFromString($termString);
                    $ip6List[] = $ip;
                } elseif ($mechanismName === 'include') {
                    $domain = $this->getDomainFromMechanism($term, $termString);
                    $includes[] = $domain;
                } elseif ($mechanismName === 'all') {
                    $allQualifier = $term->getQualifier();
                } else {
                    $otherMechanisms[] = $termString;
                }
            }
            
            return [
                'ip4' => $ip4List,
                'ip6' => $ip6List,
                'includes' => $includes,
                'other' => $otherMechanisms,
                'all_qualifier' => $allQualifier,
                'lookup_reduction' => count($includes) > 0 ? 'Consider flattening includes' : 'Already optimal',
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to flatten SPF record: ' . $e->getMessage(),
            ];
        }
    }

    // ============================================
    // SPF CHECKING FUNCTIONALITY
    // ============================================

    /**
     * Check SPF record for a specific domain
     *
     * @param string $domain
     * @return SpfCheck|null
     */
    public function checkDomain(string $domain): ?SpfCheck
    {
        try {
            // Check if we have a recent cached result
            $cacheKey = 'spf_check_' . md5($domain);
            if (Cache::has($cacheKey)) {
                $cached = Cache::get($cacheKey);
                // Verify we got a valid object
                if ($cached instanceof SpfCheck) {
                    return $cached;
                }
                // If not valid, remove from cache
                Cache::forget($cacheKey);
            }
            
            // Perform DNS lookup
            $spfRecord = $this->getDnsSpfRecord($domain);
            
            // Find or create the check record
            $check = SpfCheck::firstOrNew(['domain' => $domain]);
            
            // Try to get domain_id if it exists
            $domainModel = Domain::where('domain', $domain)->first();
            if ($domainModel) {
                $check->domain_id = $domainModel->domain_id;
            }
            
            if ($spfRecord) {
                // Parse and validate the SPF record
                $parsed = $this->parseSpfRecord($spfRecord, $domain);
                
                $check->record = $spfRecord;
                $check->valid = $parsed['valid'];
                $check->policy = $parsed['policy'];
                $check->spf_version = $parsed['version'] ?? 'v=spf1';
                $check->lookup_count = $parsed['lookup_count'] ?? 0;
                $check->mechanisms = json_encode($parsed['mechanisms'] ?? []);
                $check->includes = json_encode($parsed['includes'] ?? []);
                $check->ip4 = json_encode($parsed['ip4'] ?? []);
                $check->ip6 = json_encode($parsed['ip6'] ?? []);
                $check->mx_domains = json_encode($parsed['mx_domains'] ?? []);
                $check->a_domains = json_encode($parsed['a_domains'] ?? []);
                $check->modifiers = json_encode($parsed['modifiers'] ?? []);
                $check->has_ptr = $parsed['has_ptr'] ?? false;
                $check->has_exists = $parsed['has_exists'] ?? false;
                $check->mechanism_count = $parsed['mechanism_count'] ?? 0;
                $check->validation_issues = json_encode($parsed['validation_issues'] ?? []);
                $check->error_message = null;
            } else {
                $check->valid = false;
                $check->error_message = 'No SPF record found for domain';
                $check->record = null;
                $check->validation_issues = null;
            }
            
            $check->last_checked_at = now();
            $check->next_check_at = now()->addHours(24);
            $check->save();
            
            // Reload the model to ensure we have a fresh instance
            $check = $check->fresh();
            
            // Cache the result for 1 hour
            Cache::put($cacheKey, $check, 3600);
            
            return $check;
            
        } catch (\Exception $e) {
            Log::error('SPF check failed for domain ' . $domain . ': ' . $e->getMessage());
            
            try {
                $check = SpfCheck::firstOrNew(['domain' => $domain]);
                $check->valid = false;
                $check->error_message = 'Error checking SPF: ' . $e->getMessage();
                $check->last_checked_at = now();
                $check->save();
                return $check;
            } catch (\Exception $e2) {
                Log::error('Failed to save SPF error for domain ' . $domain . ': ' . $e2->getMessage());
                return null;
            }
        }
    }
    
    /**
     * Get DNS SPF record for a domain
     *
     * @param string $domain
     * @return string|null
     */
    protected function getDnsSpfRecord(string $domain): ?string
    {
        $records = dns_get_record($domain, DNS_TXT);
        
        foreach ($records as $record) {
            if (isset($record['txt']) && str_starts_with($record['txt'], 'v=spf1')) {
                return $record['txt'];
            }
        }
        
        return null;
    }
    
    /**
     * Parse and validate an SPF record using the Decoder
     *
     * @param string $spfString
     * @param string $domain
     * @return array
     */
    protected function parseSpfRecord(string $spfString, string $domain): array
    {
        $result = [
            'valid' => false,
            'policy' => null,
            'version' => 'v=spf1',
            'lookup_count' => 0,
            'mechanisms' => [],
            'includes' => [],
            'ip4' => [],
            'ip6' => [],
            'mx_domains' => [],
            'a_domains' => [],
            'modifiers' => [],
            'has_ptr' => false,
            'has_exists' => false,
            'mechanism_count' => 0,
            'validation_issues' => [],
        ];
        
        try {
            // Use the decoder to parse the SPF record
            $record = $this->decoder->getRecordFromTXT($spfString);
            
            if ($record === null) {
                $result['validation_issues'][] = [
                    'level' => 'error',
                    'message' => 'Not a valid SPF record',
                    'term' => null,
                ];
                return $result;
            }
            
            $terms = $record->getTerms();
            
            $lookupCount = 0;
            $mechanisms = [];
            $validationIssues = [];
            
            foreach ($terms as $term) {
                $termString = (string) $term;
                $result['mechanism_count']++;
                
                // Get the mechanism name
                $mechanismName = $term->getName();
                
                // Categorize different mechanism types using getName()
                if ($mechanismName === 'all') {
                    $qualifier = $term->getQualifier();
                    $result['policy'] = match($qualifier) {
                        Mechanism::QUALIFIER_FAIL => '-all',
                        Mechanism::QUALIFIER_SOFTFAIL => '~all',
                        Mechanism::QUALIFIER_NEUTRAL => '?all',
                        Mechanism::QUALIFIER_PASS => '+all',
                        default => null,
                    };
                    $mechanisms[] = ['type' => 'all', 'value' => $termString];
                } elseif ($mechanismName === 'include') {
                    $includeDomain = $this->getDomainFromMechanism($term, $termString);
                    $result['includes'][] = $includeDomain;
                    $lookupCount++;
                    $mechanisms[] = ['type' => 'include', 'value' => $termString, 'domain' => $includeDomain];
                } elseif ($mechanismName === 'ip4') {
                    $ipString = $this->extractDomainFromString($termString);
                    $result['ip4'][] = $ipString;
                    $mechanisms[] = ['type' => 'ip4', 'value' => $termString, 'ip' => $ipString];
                } elseif ($mechanismName === 'ip6') {
                    $ipString = $this->extractDomainFromString($termString);
                    $result['ip6'][] = $ipString;
                    $mechanisms[] = ['type' => 'ip6', 'value' => $termString, 'ip' => $ipString];
                } elseif ($mechanismName === 'mx') {
                    $mxDomain = $this->getDomainFromMechanism($term, $termString) ?: $domain;
                    $result['mx_domains'][] = $mxDomain;
                    $lookupCount++;
                    $mechanisms[] = ['type' => 'mx', 'value' => $termString, 'domain' => $mxDomain];
                } elseif ($mechanismName === 'a') {
                    $aDomain = $this->getDomainFromMechanism($term, $termString) ?: $domain;
                    $result['a_domains'][] = $aDomain;
                    $lookupCount++;
                    $mechanisms[] = ['type' => 'a', 'value' => $termString, 'domain' => $aDomain];
                } elseif ($mechanismName === 'ptr') {
                    $result['has_ptr'] = true;
                    $lookupCount++;
                    $mechanisms[] = ['type' => 'ptr', 'value' => $termString];
                } elseif ($mechanismName === 'exists') {
                    $existsDomain = $this->getDomainFromMechanism($term, $termString);
                    $result['has_exists'] = true;
                    $lookupCount++;
                    $mechanisms[] = ['type' => 'exists', 'value' => $termString, 'domain' => $existsDomain];
                } elseif ($mechanismName === 'redirect') {
                    $redirectDomain = $this->getDomainFromMechanism($term, $termString);
                    $result['modifiers'][] = ['type' => 'redirect', 'value' => $termString, 'domain' => $redirectDomain];
                    $lookupCount++;
                } elseif ($mechanismName === 'exp') {
                    $expDomain = $this->getDomainFromMechanism($term, $termString);
                    $result['modifiers'][] = ['type' => 'exp', 'value' => $termString, 'domain' => $expDomain];
                } else {
                    // Unknown mechanism type - add as is
                    $mechanisms[] = ['type' => $mechanismName, 'value' => $termString];
                }
            }
            
            $result['lookup_count'] = $lookupCount;
            $result['mechanisms'] = $mechanisms;
            
            // Validate the record using SemanticValidator
            $validator = new SemanticValidator();
            $validationIssues = $validator->validate($record);
            
            foreach ($validationIssues as $issue) {
                $result['validation_issues'][] = [
                    'level' => $issue->getLevel(),
                    'message' => $issue->getMessage(),
                    'term' => $issue->getTerm() ? (string) $issue->getTerm() : null,
                ];
            }
            
            // Check if record is valid (has all mechanism and no critical issues)
            $result['valid'] = !empty($result['policy']) && empty(array_filter($validationIssues, function($issue) {
                return $issue->getLevel() === 'error' || $issue->getLevel() === 'fatal';
            }));
            
        } catch (Exception $e) {
            $result['validation_issues'][] = [
                'level' => 'error',
                'message' => 'Failed to parse SPF record: ' . $e->getMessage(),
                'term' => null,
            ];
        } catch (\Exception $e) {
            $result['validation_issues'][] = [
                'level' => 'error',
                'message' => 'Unexpected error: ' . $e->getMessage(),
                'term' => null,
            ];
        }
        
        return $result;
    }
    
    /**
     * Extract domain from SPF string
     *
     * @param string $string
     * @return string
     */
    protected function extractDomainFromString(string $string): string
    {
        // Remove the qualifier (+/-/~/?)
        $string = ltrim($string, '+~?-');
        
        // Remove the mechanism name prefix if present (include:, a:, mx:, etc.)
        $parts = explode(':', $string, 2);
        if (count($parts) === 2) {
            // Check if the first part is a known mechanism name
            $knownMechanisms = ['include', 'a', 'mx', 'exists', 'ptr', 'ip4', 'ip6'];
            if (in_array($parts[0], $knownMechanisms)) {
                return trim($parts[1]);
            }
        }
        
        // Check for equals separator (redirect=domain.com, exp=domain.com)
        if (strpos($string, '=') !== false) {
            $parts = explode('=', $string, 2);
            return trim($parts[1]);
        }
        
        // If no separator, return the string as is
        return trim($string);
    }
    
    /**
     * Get the domain from a mechanism using the proper methods
     *
     * @param Mechanism $mechanism
     * @param string $termString
     * @return string
     */
    protected function getDomainFromMechanism(Mechanism $mechanism, string $termString): string
    {
        // Check if the mechanism implements TermWithDomainSpec
        if ($mechanism instanceof \SPFLib\Term\TermWithDomainSpec) {
            $domainSpec = $mechanism->getDomainSpec();
            if ($domainSpec !== null && !$domainSpec->isEmpty()) {
                return (string) $domainSpec;
            }
        }
        
        // Fallback: extract from string
        return $this->extractDomainFromString($termString);
    }
    
    /**
     * Check all domains
     *
     * @return int Number of domains checked
     */
    public function checkAllDomains(): int
    {
        $domains = Domain::all();
        $count = 0;
        
        foreach ($domains as $domain) {
            $this->checkDomain($domain->domain);
            $count++;
            
            // Avoid rate limiting
            if ($count % 10 === 0) {
                usleep(100000); // 0.1 second delay every 10 domains
            }
        }
        
        return $count;
    }
    
    /**
     * Check domains that need updating
     *
     * @return int Number of domains checked
     */
    public function checkDomainsNeedingUpdate(): int
    {
        try {
            $domains = Domain::whereDoesntHave('spfCheck', function($query) {
                $query->where('next_check_at', '>', now());
            })->get();
        } catch (\Exception $e) {
            // Fallback if relationship doesn't exist
            $checkedDomains = SpfCheck::where('next_check_at', '>', now())
                ->pluck('domain')
                ->toArray();
                
            $domains = Domain::whereNotIn('domain', $checkedDomains)->get();
        }
        
        $count = 0;
        
        foreach ($domains as $domain) {
            $result = $this->checkDomain($domain->domain);
            if ($result !== null) {
                $count++;
            }
            
            if ($count % 10 === 0) {
                usleep(100000);
            }
        }
        
        return $count;
    }
    
    /**
     * Get SPF statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        $total = SpfCheck::count();
        $valid = SpfCheck::where('valid', true)->count();
        $noRecord = SpfCheck::whereNull('record')->count();
        $invalid = SpfCheck::where('valid', false)->whereNotNull('record')->count();
        
        // Policy breakdown
        $policies = SpfCheck::whereNotNull('policy')
            ->select('policy', \DB::raw('count(*) as count'))
            ->groupBy('policy')
            ->pluck('count', 'policy')
            ->toArray();
        
        // Lookup count distribution
        $lookupDistribution = SpfCheck::whereNotNull('lookup_count')
            ->select('lookup_count', \DB::raw('count(*) as count'))
            ->groupBy('lookup_count')
            ->orderBy('lookup_count')
            ->pluck('count', 'lookup_count')
            ->toArray();
        
        // High lookup count domains (> 10)
        $highLookups = SpfCheck::where('lookup_count', '>', 10)
            ->where('valid', true)
            ->pluck('domain')
            ->toArray();
        
        return [
            'total' => $total,
            'valid' => $valid,
            'no_record' => $noRecord,
            'invalid' => $invalid,
            'policies' => $policies,
            'lookup_distribution' => $lookupDistribution,
            'high_lookups' => $highLookups,
            'high_lookup_count' => count($highLookups),
        ];
    }
}