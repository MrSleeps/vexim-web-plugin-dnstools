<?php

namespace VEximweb\Plugin\DnsTools\Services;

use SPFLib\Record;
use SPFLib\Term\Mechanism;
use SPFLib\Term\Modifier;
use SPFLib\SemanticValidator;
use SPFLib\Exception\InvalidTermException;
use VEximweb\Plugin\DnsTools\Models\SystemDomains as Domain;
use Illuminate\Support\Facades\Log;

class SpfRecordService
{
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
            
            // Add the version mechanism (v=spf1)
            $record->addTerm(new Mechanism\VersionMechanism());
            
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
                        $record->addTerm(
                            new Mechanism\Ip4Mechanism(
                                Mechanism::QUALIFIER_PASS,
                                $ipv4['ipv4']
                            )
                        );
                    }
                }
            }
            
            // 3. Add IPv6 addresses
            if (!empty($data['spf_ipv6'])) {
                foreach ($data['spf_ipv6'] as $ipv6) {
                    if (!empty($ipv6['ipv6'])) {
                        $record->addTerm(
                            new Mechanism\Ip6Mechanism(
                                Mechanism::QUALIFIER_PASS,
                                $ipv6['ipv6']
                            )
                        );
                    }
                }
            }
            
            // 4. Add MX mechanism
            if (!empty($data['spf_use_mx'])) {
                if (!empty($data['spf_mx_domain'])) {
                    // Custom MX domain
                    $record->addTerm(
                        new Mechanism\MxMechanism(
                            Mechanism::QUALIFIER_PASS,
                            $data['spf_mx_domain']
                        )
                    );
                } else {
                    // Use current domain's MX records
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
                    // Custom A domain
                    $record->addTerm(
                        new Mechanism\AMechanism(
                            Mechanism::QUALIFIER_PASS,
                            $data['spf_a_domain']
                        )
                    );
                } else {
                    // Use current domain's A record
                    $record->addTerm(
                        new Mechanism\AMechanism(
                            Mechanism::QUALIFIER_PASS
                        )
                    );
                }
            }
            
            // 6. Add Advanced mechanisms
            // PTR (deprecated - use with caution)
            if (!empty($data['spf_ptr']) && $data['spf_ptr'] === 'ptr') {
                $record->addTerm(
                    new Mechanism\PtrMechanism(
                        Mechanism::QUALIFIER_PASS
                    )
                );
            }
            
            // Exists mechanism
            if (!empty($data['spf_exists'])) {
                $record->addTerm(
                    new Mechanism\ExistsMechanism(
                        Mechanism::QUALIFIER_PASS,
                        $data['spf_exists']
                    )
                );
            }
            
            // 7. Add Redirect modifier (must be added before the ALL mechanism)
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
            
            // Log any issues
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
                'record' => 'v=spf1 -all', // Fallback to a safe default
                'lookups' => 0,
                'issues' => ['Error: ' . $e->getMessage()],
                'is_valid' => false,
            ];
        }
    }
    
    /**
     * Get the qualifier for the ALL mechanism
     *
     * @param string $policy
     * @return int
     */
    protected function getQualifier(string $policy): int
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
                    'level' => $issue->getLevel(), // 'info', 'warning', 'error'
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
            // Mechanisms that cause DNS lookups
            if ($term instanceof Mechanism\IncludeMechanism ||
                $term instanceof Mechanism\MxMechanism ||
                $term instanceof Mechanism\AMechanism ||
                $term instanceof Mechanism\PtrMechanism ||
                $term instanceof Mechanism\ExistsMechanism) {
                $lookupCount++;
            }
            
            // Redirect modifier also causes a lookup
            if ($term instanceof Modifier\RedirectModifier) {
                $lookupCount++;
            }
        }
        
        return $lookupCount;
    }
    
    /**
     * Test if an IP address is allowed by the SPF record
     *
     * @param string $ip
     * @param string $domain
     * @param array $data
     * @return bool
     */
    public function testIp(string $ip, string $domain, array $data): bool
    {
        try {
            // Generate the record first
            $result = $this->generate(new Domain(['domain' => $domain]), $data);
            
            // Parse the record
            $record = Record::fromString($result['record']);
            
            // Check if the IP is allowed
            // Note: This is a simplified check. In production, you'd use a proper SPF checker
            // that performs actual DNS lookups. The mlocati/spf-lib doesn't have a built-in
            // checker, so you might want to use a separate package for this.
            
            return true; // Placeholder
        } catch (\Exception $e) {
            Log::error('Failed to test SPF record: ' . $e->getMessage());
            return false;
        }
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
            $record = Record::fromString($recordString);
            $terms = $record->getTerms();
            
            $description = [
                'version' => 'v=spf1',
                'mechanisms' => [],
                'modifiers' => [],
                'summary' => '',
            ];
            
            foreach ($terms as $term) {
                if ($term instanceof Mechanism\VersionMechanism) {
                    continue;
                }
                
                $termString = (string) $term;
                
                if ($term instanceof Mechanism\AllMechanism) {
                    $description['mechanisms'][] = [
                        'type' => 'all',
                        'value' => $termString,
                        'description' => $this->getMechanismDescription($term),
                    ];
                } elseif ($term instanceof Mechanism\IncludeMechanism) {
                    $description['mechanisms'][] = [
                        'type' => 'include',
                        'value' => $termString,
                        'domain' => $term->getDomain(),
                        'description' => 'Include SPF records from ' . $term->getDomain(),
                    ];
                } elseif ($term instanceof Mechanism\Ip4Mechanism) {
                    $description['mechanisms'][] = [
                        'type' => 'ip4',
                        'value' => $termString,
                        'ip' => $term->getIp(),
                        'description' => 'Allow IP ' . $term->getIp(),
                    ];
                } elseif ($term instanceof Mechanism\Ip6Mechanism) {
                    $description['mechanisms'][] = [
                        'type' => 'ip6',
                        'value' => $termString,
                        'ip' => $term->getIp(),
                        'description' => 'Allow IP ' . $term->getIp(),
                    ];
                } elseif ($term instanceof Mechanism\MxMechanism) {
                    $description['mechanisms'][] = [
                        'type' => 'mx',
                        'value' => $termString,
                        'domain' => $term->getDomain() ?? 'current domain',
                        'description' => 'Allow MX servers for ' . ($term->getDomain() ?? 'this domain'),
                    ];
                } elseif ($term instanceof Mechanism\AMechanism) {
                    $description['mechanisms'][] = [
                        'type' => 'a',
                        'value' => $termString,
                        'domain' => $term->getDomain() ?? 'current domain',
                        'description' => 'Allow A record for ' . ($term->getDomain() ?? 'this domain'),
                    ];
                } elseif ($term instanceof Modifier\RedirectModifier) {
                    $description['modifiers'][] = [
                        'type' => 'redirect',
                        'value' => $termString,
                        'domain' => $term->getDomain(),
                        'description' => 'Redirect to ' . $term->getDomain(),
                    ];
                } elseif ($term instanceof Modifier\ExpModifier) {
                    $description['modifiers'][] = [
                        'type' => 'exp',
                        'value' => $termString,
                        'domain' => $term->getDomain(),
                        'description' => 'Explanation: ' . $term->getDomain(),
                    ];
                } else {
                    $description['mechanisms'][] = [
                        'type' => 'unknown',
                        'value' => $termString,
                        'description' => 'Unknown mechanism',
                    ];
                }
            }
            
            // Generate a summary
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
     * @param mixed $mechanism
     * @return string
     */
    protected function getMechanismDescription($mechanism): string
    {
        if ($mechanism instanceof Mechanism\AllMechanism) {
            $qualifier = $mechanism->getQualifier();
            return match($qualifier) {
                Mechanism::QUALIFIER_FAIL => 'Reject all other senders (Hard Fail)',
                Mechanism::QUALIFIER_SOFTFAIL => 'Accept but mark as suspicious (Soft Fail)',
                Mechanism::QUALIFIER_NEUTRAL => 'Do nothing (Neutral)',
                Mechanism::QUALIFIER_PASS => 'Allow all senders (DANGEROUS)',
                default => 'Unknown qualifier',
            };
        }
        return 'SPF mechanism';
    }
    
    /**
     * Flatten a complex SPF record to its simplest form
     * (Useful for reducing lookups)
     *
     * @param string $recordString
     * @return array
     */
    public function flattenRecord(string $recordString): array
    {
        try {
            $record = Record::fromString($recordString);
            $terms = $record->getTerms();
            
            $ip4List = [];
            $ip6List = [];
            $includes = [];
            $otherMechanisms = [];
            $allQualifier = null;
            
            foreach ($terms as $term) {
                if ($term instanceof Mechanism\Ip4Mechanism) {
                    $ip4List[] = $term->getIp();
                } elseif ($term instanceof Mechanism\Ip6Mechanism) {
                    $ip6List[] = $term->getIp();
                } elseif ($term instanceof Mechanism\IncludeMechanism) {
                    $includes[] = $term->getDomain();
                } elseif ($term instanceof Mechanism\AllMechanism) {
                    $allQualifier = $term->getQualifier();
                } else {
                    $otherMechanisms[] = (string) $term;
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
}