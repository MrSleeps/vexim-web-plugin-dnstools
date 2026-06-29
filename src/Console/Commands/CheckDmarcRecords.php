<?php

namespace VEximweb\Plugin\DnsTools\Console\Commands;

use Illuminate\Console\Command;
use VEximweb\Plugin\DnsTools\Dmarc\Services\DmarcCheckService;

class CheckDmarcRecords extends Command
{
    protected $signature = 'dmarc:check 
                            {--domain= : Check a specific domain} 
                            {--all : Check all domains}
                            {--stats : Show DMARC statistics}';
    protected $description = 'Check DMARC records for domains';
    
    public function handle(DmarcCheckService $service): void
    {
        if ($this->option('stats')) {
            $stats = $service->getStats();
            
            $this->info("DMARC Statistics:");
            $this->line("Total domains checked: {$stats['total']}");
            $this->line("Valid DMARC records: {$stats['valid']}");
            $this->line("No DMARC record: {$stats['no_record']}");
            $this->line("Invalid records: {$stats['invalid']}");
            
            if (!empty($stats['policies'])) {
                $this->line("\nPolicy Breakdown:");
                foreach ($stats['policies'] as $policy => $count) {
                    $label = match($policy) {
                        'none' => 'Monitor',
                        'quarantine' => 'Quarantine',
                        'reject' => 'Reject',
                        default => $policy,
                    };
                    $this->line("  {$label}: {$count}");
                }
            }
            return;
        }
        
        if ($this->option('domain')) {
            $domain = $this->option('domain');
            $this->info("Checking DMARC for: {$domain}");
            
            $result = $service->checkDomain($domain);
            
            if ($result && $result->valid) {
                $this->info("✓ DMARC record found!");
                $this->line("Policy: {$result->getPolicyLabel()}");
                $this->line("Record: {$result->record}");
            } else {
                $this->error("✗ No valid DMARC record found");
                if ($result) {
                    $this->line("Error: {$result->error_message}");
                }
            }
            return;
        }
        
        if ($this->option('all')) {
            $this->info('Checking DMARC for all domains...');
            $service->checkAllDomains();
            $this->info('Done!');
            return;
        }
        
        // Default: check domains that need updating
        $this->info('Checking domains that need DMARC updates...');
        $service->checkDomainsNeedingUpdate();
        $this->info('Done!');
    }
}
