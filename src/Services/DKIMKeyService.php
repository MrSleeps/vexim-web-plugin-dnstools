<?php

namespace VEximweb\Plugin\DnsTools\Services;

use phpseclib3\Crypt\RSA;
use VEximweb\Core\Data\Models\Domain;
use VEximweb\Core\Data\Models\DKIM;

class DKIMKeyService
{
    /**
     * Generate DKIM key pair for a domain
     *
     * @param Domain $domain
     * @param string $selector
     * @return DKIM
     */
    public function generateKeys(Domain $domain, string $selector = 'default'): DKIM
    {
        // Generate RSA key pair using phpseclib
        $privateKey = RSA::createKey(2048);
        $publicKey = $privateKey->getPublicKey();
        
        // Format private key for storage (PEM format)
        $privateKeyPem = (string) $privateKey;
        
        // Extract public key content for DNS (remove PEM headers)
        $publicKeyString = str_replace(
            ["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\n", "\r"],
            "",
            (string) $publicKey
        );
        
        $dnsRecord = "v=DKIM1; k=rsa; p=" . $publicKeyString;

        return DKIM::updateOrCreate(
            [
                'domain_id' => $domain->domain_id,
                'selector' => $selector,
            ],
            [
                'private_key' => $privateKeyPem,
                'public_key' => $publicKeyString,
                'canonical' => 'relaxed',
                'enabled' => true,
            ]
        );
    }
    
    /**
     * Get DNS record details for a domain's DKIM key
     *
     * @param DKIM $dkim
     * @param Domain $domain
     * @return array
     */
    public function getDNSRecord(DKIM $dkim, Domain $domain): array
    {
        return [
            'name' => $dkim->selector . '._domainkey.' . $domain->domain,
            'type' => 'TXT',
            'value' => "v=DKIM1; k=rsa; p=" . $dkim->public_key,
            'ttl' => 3600,
        ];
    }
    
    /**
     * Validate that DKIM keys are properly formatted
     *
     * @param string $privateKey
     * @return bool
     */
    public function validatePrivateKey(string $privateKey): bool
    {
        try {
            RSA::load($privateKey);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
