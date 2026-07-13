<?php

namespace VEximweb\Plugin\DnsTools\Console\Commands;

use Illuminate\Console\Command;
use VEximweb\Plugin\DnsTools\Services\MtaStsService;
use VEximweb\Plugin\DnsTools\Models\MtaStsCheck;
use VEximweb\Core\Data\Models\Domain;

class CheckMtaStsRecords extends Command
{
    protected $signature = 'vw:mta-sts-check 
                            {--domain= : Check a specific domain} 
                            {--all : Check all domains}
                            {--stats : Show MTA-STS statistics}
                            {--fetch : Fetch and validate the .well-known/mta-sts.txt file}
                            {--mx-check : Compare MX records against the policy file}
                            {--domain-id= : Domain ID for the record (optional, will be auto-detected)}
                            {--debug : Show debug information}';
    
    protected $description = 'Check MTA-STS records for domains';
    
    public function handle(MtaStsService $service): void
    {
        if ($this->option('stats')) {
            $this->showStats($service);
            return;
        }
        
        if ($this->option('domain')) {
            $this->checkSingleDomain($service);
            return;
        }
        
        if ($this->option('all')) {
            $this->checkAllDomains($service);
            return;
        }
        
        // Default: check domains that need updating
        $this->info('Checking domains that need MTA-STS updates...');
        
        // Debug: Show what domains exist
        if ($this->option('debug')) {
            $this->showDebugInfo();
        }
        
        $count = $service->checkDomainsNeedingUpdate();
        $this->info("Done! Checked {$count} domains.");
    }
    
    protected function showDebugInfo(): void
    {
        $this->line("");
        $this->info("Debug Information:");
        
        $totalDomains = Domain::where('enabled', true)->count();
        $this->line("  Total enabled domains: {$totalDomains}");
        
        $existingRecords = MtaStsCheck::count();
        $this->line("  Existing MTA-STS records: {$existingRecords}");
        
        $needsUpdate = MtaStsCheck::needsUpdate()->count();
        $this->line("  Records needing update: {$needsUpdate}");
        
        $domainsWithoutRecord = Domain::where('enabled', true)
            ->whereNotIn('domain', MtaStsCheck::pluck('domain')->toArray())
            ->count();
        $this->line("  Domains without any record: {$domainsWithoutRecord}");
        
        // Show first 5 domains
        $domains = Domain::where('enabled', true)->limit(5)->get();
        $this->line("");
        $this->line("  First 5 enabled domains:");
        foreach ($domains as $domain) {
            $hasRecord = MtaStsCheck::where('domain', $domain->domain)->exists();
            $this->line("    - {$domain->domain} (domain_id: {$domain->domain_id})" . ($hasRecord ? " ✓ has record" : " ✗ no record"));
        }
        
        $this->line("");
    }
    
    protected function showStats(MtaStsService $service): void
    {
        $stats = $service->getStats();
        
        $this->info("MTA-STS Statistics:");
        $this->line("");
        $this->line("Overall:");
        $this->line("  Total domains (enabled): {$stats['total_domains']}");
        $this->line("  Domains checked: {$stats['total']}");
        $this->line("  Coverage: {$stats['coverage']}%");
        
        $this->line("");
        $this->line("DNS Records:");
        $this->line("  Valid DNS records: {$stats['valid']}");
        $this->line("  No DNS record: {$stats['no_record']}");
        $this->line("  Invalid records: {$stats['invalid']}");
        
        if (!empty($stats['modes'])) {
            $this->line("");
            $this->line("Mode Breakdown:");
            foreach ($stats['modes'] as $mode => $count) {
                $label = match($mode) {
                    'enforce' => 'Enforce (Strict)',
                    'testing' => 'Testing',
                    'none' => 'None (Disabled)',
                    default => $mode ?: 'Unknown',
                };
                $this->line("  {$label}: {$count}");
            }
        }
        
        if (!empty($stats['max_age_distribution'])) {
            $this->line("");
            $this->line("Max Age Distribution:");
            foreach ($stats['max_age_distribution'] as $maxAge => $count) {
                $this->line("  {$maxAge}: {$count} domains");
            }
        }
        
        $this->line("");
        $this->line("Policy Files:");
        $this->line("  Valid policies: {$stats['policy_valid_count']}");
        $this->line("  Invalid policies: {$stats['policy_invalid_count']}");
        
        if ($stats['expired_count'] > 0) {
            $this->line("");
            $this->warn("Expired Policies ({$stats['expired_count']}):");
            foreach (array_slice($stats['expired_domains'], 0, 10) as $domain) {
                $this->line("  - {$domain}");
            }
            if ($stats['expired_count'] > 10) {
                $this->line("  ... and " . ($stats['expired_count'] - 10) . " more");
            }
        }
        
        if ($stats['mx_mismatch_count'] > 0) {
            $this->line("");
            $this->warn("MX Mismatches ({$stats['mx_mismatch_count']}):");
            foreach (array_slice($stats['mx_mismatch_domains'], 0, 10) as $domain) {
                $this->line("  - {$domain}");
            }
            if ($stats['mx_mismatch_count'] > 10) {
                $this->line("  ... and " . ($stats['mx_mismatch_count'] - 10) . " more");
            }
        }
        
        $this->line("");
        $this->info("Tip: Use --domain=example.com for detailed checks");
    }
    
    protected function checkSingleDomain(MtaStsService $service): void
    {
        $domain = $this->option('domain');
        $domainId = $this->option('domain-id');
        
        // Auto-detect domain_id if not provided
        if (!$domainId) {
            $domainModel = Domain::where('domain', $domain)->first();
            if ($domainModel) {
                $domainId = $domainModel->domain_id;
            }
        }
        
        $this->info("Checking MTA-STS for: {$domain}");
        $this->line("");
        
        $result = $service->checkDomain($domain, $domainId);
        
        // Get the saved record for more details
        $record = MtaStsCheck::where('domain', $domain)->first();
        
        if ($result && $result->valid) {
            $this->info("MTA-STS DNS record found!");
            $this->line("");
            $this->line("  DNS Record: {$result->dns_record}");
            $this->line("  DNS ID: {$result->dns_id}");
            $this->line("");
            $this->line("  Policy Mode: {$result->mode}");
            $this->line("  Policy Max Age: {$result->max_age}s");
            if ($result->mx_record) {
                $this->line("  Policy MX Records: " . implode(', ', (array)$result->mx_record));
            }
            
            // Show expiry status
            if ($record && $record->dns_expires_at) {
                if ($record->isExpired()) {
                    $this->warn("  Policy is EXPIRED (since {$record->dns_expires_at->diffForHumans()})");
                } else {
                    $this->line("  Policy expires: {$record->dns_expires_at->diffForHumans()}");
                }
            }
            
            if ($this->option('fetch') || $this->option('mx-check')) {
                $this->line("");
                $this->info("Fetching policy file...");
                $policyFile = $service->fetchPolicyFile($domain);
                
                if ($policyFile) {
                    $this->info("Policy file found!");
                    $this->line("");
                    $this->line("  Version: {$policyFile['version']}");
                    $this->line("  Mode: {$policyFile['mode']}");
                    $this->line("  Max Age: {$policyFile['max_age']}s");
                    if (isset($policyFile['mx'])) {
                        $this->line("  MX Records: " . implode(', ', $policyFile['mx']));
                    }
                    
                    if ($this->option('mx-check')) {
                        $record = MtaStsCheck::where('domain', $domain)->first();
                        if ($record && $record->mx_validation_details) {
                            $mxCheck = $record->mx_validation_details;
                            
                            $this->line("");
                            $this->info("MX Record Validation:");
                            
                            if ($mxCheck['valid']) {
                                $this->info("  All MX records match the policy file!");
                            } else {
                                $this->error("  MX mismatch detected!");
                                $this->line("  MX records in DNS: " . implode(', ', $mxCheck['dns_mx'] ?? []));
                                $this->line("  MX records in policy: " . implode(', ', $mxCheck['policy_mx'] ?? []));
                                
                                if (!empty($mxCheck['missing_in_policy'])) {
                                    $this->warn("  Missing in policy: " . implode(', ', $mxCheck['missing_in_policy']));
                                }
                                if (!empty($mxCheck['missing_in_dns'])) {
                                    $this->warn("  Missing in DNS: " . implode(', ', $mxCheck['missing_in_dns']));
                                }
                            }
                        }
                    }
                } else {
                    $this->error("Could not fetch policy file");
                    $this->line("  Policy file should be at: https://mta-sts.{$domain}/.well-known/mta-sts.txt");
                }
            }
        } else {
            $this->error("No valid MTA-STS record found");
            if ($result) {
                $this->line("  Error: {$result->error_message}");
                $this->line("  Raw DNS record: " . ($result->dns_record ?? 'Not found'));
            }
            
            $this->line("");
            $this->line("A valid MTA-STS DNS record should look like:");
            $this->line("  v=STSv1; id=123456789");
            $this->line("");
            $this->line("The policy file should be at:");
            $this->line("  https://mta-sts.{$domain}/.well-known/mta-sts.txt");
            $this->line("");
            $this->line("And contain:");
            $this->line("  version: STSv1");
            $this->line("  mode: enforce");
            $this->line("  max_age: 86400");
            $this->line("  mx: mail.example.com");
        }
    }
    
    protected function checkAllDomains(MtaStsService $service): void
    {
        $totalDomains = Domain::where('enabled', true)->count();
        $this->info("Checking MTA-STS for all {$totalDomains} enabled domains...");
        $this->line("");
        
        $progress = $this->output->createProgressBar($totalDomains);
        $progress->start();
        
        $count = $service->checkAllDomains();
        $progress->finish();
        
        $this->line("");
        $this->info("Done! Checked {$count} domains.");
        
        if ($this->option('mx-check')) {
            $this->line("");
            $this->info("Validating MX records against policy files...");
            
            $records = MtaStsCheck::where('dns_valid', true)
                ->whereNotNull('dns_mx')
                ->get();
                
            $total = $records->count();
            $mismatches = 0;
            
            $progress = $this->output->createProgressBar($total);
            $progress->start();
            
            foreach ($records as $record) {
                $policy = [
                    'version' => 'STSv1',
                    'mode' => $record->dns_mode,
                    'max_age' => $record->dns_max_age,
                    'mx' => explode(',', $record->dns_mx ?? ''),
                ];
                
                $mxCheck = $service->validateMxAgainstPolicy($record->domain, $policy);
                
                $record->mx_mismatch = !$mxCheck['valid'];
                $record->mx_validation_details = $mxCheck;
                $record->save();
                
                if (!$mxCheck['valid']) {
                    $mismatches++;
                }
                
                $progress->advance();
            }
            
            $progress->finish();
            
            $this->line("");
            $this->info("Validated {$total} domains.");
            if ($mismatches > 0) {
                $this->warn("Mismatches found: {$mismatches}");
            } else {
                $this->info("No mismatches found!");
            }
        }
    }
}