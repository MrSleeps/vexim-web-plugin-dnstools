<?php

namespace VEximweb\Plugin\DnsTools\Services;

use Illuminate\Support\Facades\Log;

class DnsToolsService
{
    /**
     * Check MX records for a domain
     * 
     * @param string $domain
     * @return array|null Array of MX records or null if none found
     */
    public function checkMxRecord(string $domain): ?array
    {
        try {
            $mxRecords = dns_get_record($domain, DNS_MX);
            
            if (empty($mxRecords)) {
                Log::info("No MX records found for domain: {$domain}");
                return null;
            }
            
            // Sort by priority
            usort($mxRecords, function($a, $b) {
                return $a['pri'] <=> $b['pri'];
            });
            
            return $mxRecords;
        } catch (\Exception $e) {
            Log::error("Error checking MX records for {$domain}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if domain has valid MX records
     */
    public function hasValidMxRecord(string $domain): bool
    {
        $records = $this->checkMxRecord($domain);
        return !empty($records);
    }
	
	function getSPFRecord(string $domain) {
		$dnsRecords = dns_get_record($domain, DNS_TXT);

		if (!$dnsRecords) {
			return "Failed to fetch DNS records.";
		}

		foreach ($dnsRecords as $record) {
			if (isset($record['txt']) && stripos($record['txt'], 'v=spf1') === 0) {
				return $record['txt'];
			}
		}

		return "No SPF record found.";
	}	
}