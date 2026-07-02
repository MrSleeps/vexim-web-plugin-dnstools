<?php

namespace VEximweb\Plugin\DnsTools\Console\Commands;

use Illuminate\Console\Command;
use VEximweb\Plugin\DnsTools\Services\SpfRecordService;

class CheckSpfRecords extends Command
{
    protected $signature = 'vw:spf-check 
                            {--domain= : Check a specific domain} 
                            {--all : Check all domains}
                            {--stats : Show SPF statistics}';
    protected $description = 'Check SPF records for domains';
    
    public function handle(SpfRecordService $service): void
    {
        if ($this->option('stats')) {
            $stats = $service->getStats();
            
            $this->info("SPF Statistics:");
            $this->line("Total domains checked: {$stats['total']}");
            $this->line("Valid SPF records: {$stats['valid']}");
            $this->line("No SPF record: {$stats['no_record']}");
            $this->line("Invalid records: {$stats['invalid']}");
            
            if (!empty($stats['policies'])) {
                $this->line("\nPolicy Breakdown:");
                foreach ($stats['policies'] as $policy => $count) {
                    $label = match($policy) {
                        '-all' => 'Hard Fail',
                        '~all' => 'Soft Fail',
                        '?all' => 'Neutral',
                        '+all' => 'Pass (DANGEROUS)',
                        default => $policy,
                    };
                    $this->line("  {$label}: {$count}");
                }
            }
            
            if (!empty($stats['lookup_distribution'])) {
                $this->line("\nLookup Count Distribution:");
                foreach ($stats['lookup_distribution'] as $count => $domains) {
                    $this->line("  {$count} lookups: {$domains} domains");
                }
            }
            
            if ($stats['high_lookup_count'] > 0) {
                $this->warn("\n⚠️  Domains with >10 lookups (may cause SPF failures):");
                foreach (array_slice($stats['high_lookups'], 0, 10) as $domain) {
                    $this->line("  - {$domain}");
                }
                if ($stats['high_lookup_count'] > 10) {
                    $this->line("  ... and " . ($stats['high_lookup_count'] - 10) . " more");
                }
            }
            return;
        }
        
        if ($this->option('domain')) {
            $domain = $this->option('domain');
            $this->info("Checking SPF for: {$domain}");
            
            $result = $service->checkDomain($domain);
            
            if ($result && $result->valid) {
                $this->info("✓ SPF record found!");
                $this->line("Policy: {$result->policy}");
                $this->line("Record: {$result->record}");
                $this->line("Lookups: {$result->lookup_count}");
                
                if ($result->lookup_count > 10) {
                    $this->warn("⚠️  Warning: {$result->lookup_count} DNS lookups exceeds recommended maximum of 10");
                }
                
                // Show mechanisms
                if ($result->mechanisms) {
                    $mechanisms = json_decode($result->mechanisms, true);
                    $this->line("\nMechanisms:");
                    foreach ($mechanisms as $mech) {
                        $this->line("  - {$mech['value']}");
                    }
                }
            } else {
                $this->error("✗ No valid SPF record found");
                if ($result) {
                    $this->line("Error: {$result->error_message}");
                }
            }
            return;
        }
        
        if ($this->option('all')) {
            $this->info('Checking SPF for all domains...');
            $count = $service->checkAllDomains();
            $this->info("Done! Checked {$count} domains.");
            return;
        }
        
        // Default: check domains that need updating
        $this->info('Checking domains that need SPF updates...');
        $count = $service->checkDomainsNeedingUpdate();
        $this->info("Done! Checked {$count} domains.");
    }
}