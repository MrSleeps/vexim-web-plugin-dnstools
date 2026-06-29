<?php

declare(strict_types=1);

namespace VEximweb\Plugin\DnsTools\Dmarc;

use VEximweb\Plugin\DnsTools\Dmarc\Enums\DmarcPolicy;
use VEximweb\Plugin\DnsTools\Dmarc\Enums\DmarcAlignment;
use VEximweb\Plugin\DnsTools\Dmarc\Enums\DmarcReporting;
use VEximweb\Plugin\DnsTools\Dmarc\Enums\DmarcTesting;
use VEximweb\Plugin\DnsTools\Dmarc\Exceptions\InvalidDmarcRecordException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use JsonSerializable;

/**
 * Responsible for building an object representation of a DMARC
 * compliant string value
 *
 * @link https://mxtoolbox.com/dmarc/details/dmarc-tags
 * @link https://datatracker.ietf.org/doc/html/rfc7489
 */
class DmarcRecord implements Arrayable, Jsonable, JsonSerializable
{
    protected ?string $version = null;
    protected ?DmarcPolicy $policy = null;
    protected ?DmarcPolicy $subdomainPolicy = null;
    protected ?string $rua = null;
    protected ?string $ruf = null;
    protected ?DmarcAlignment $adkim = null;
    protected ?DmarcAlignment $aspf = null;
    protected array $reporting = [];
    protected ?int $percentage = null;
    protected ?int $reportInterval = null;
    protected ?DmarcPolicy $nonExistentSubdomainPolicy = null;
    protected ?string $publicSuffixDomainPolicy = null;
    protected ?DmarcTesting $testingMode = null;

    /**
     * Create a new DMARC record instance
     */
    public function __construct(
        ?string $version = 'DMARC1',
        DmarcPolicy|string|null $policy = DmarcPolicy::NONE,
        DmarcPolicy|string|null $subdomainPolicy = null,
        string|array|null $rua = null,
        string|array|null $ruf = null,
        DmarcAlignment|string|null $adkim = DmarcAlignment::RELAXED,
        DmarcAlignment|string|null $aspf = DmarcAlignment::RELAXED,
        array $reporting = [],
        ?int $percentage = null,
        ?int $reportInterval = null,
        DmarcPolicy|string|null $nonExistentSubdomainPolicy = null,
        ?string $publicSuffixDomainPolicy = null,
        DmarcTesting|string|null $testingMode = null
    ) {
        $this->version = $version;
        $this->setPolicy($policy);
        $this->setSubdomainPolicy($subdomainPolicy);
        $this->rua($rua);
        $this->ruf($ruf);
        $this->setAdkim($adkim);
        $this->setAspf($aspf);
        $this->reporting($reporting);
        $this->percentage($percentage);
        $this->reportInterval($reportInterval);
        $this->setNonExistentSubdomainPolicy($nonExistentSubdomainPolicy);
        $this->publicSuffixDomainPolicy($publicSuffixDomainPolicy);
        $this->setTestingMode($testingMode);
    }

    /**
     * Create a new DMARC record from an array
     */
    public static function fromArray(array $data): static
    {
        return new static(
            version: Arr::get($data, 'version', 'DMARC1'),
            policy: Arr::get($data, 'policy'),
            subdomainPolicy: Arr::get($data, 'subdomain_policy'),
            rua: Arr::get($data, 'rua'),
            ruf: Arr::get($data, 'ruf'),
            adkim: Arr::get($data, 'adkim'),
            aspf: Arr::get($data, 'aspf'),
            reporting: Arr::get($data, 'reporting', []),
            percentage: Arr::get($data, 'percentage'),
            reportInterval: Arr::get($data, 'report_interval'),
            nonExistentSubdomainPolicy: Arr::get($data, 'np'),
            publicSuffixDomainPolicy: Arr::get($data, 'psd'),
            testingMode: Arr::get($data, 't'),
        );
    }

    /**
     * Create from a DNS record string
     */
    public static function fromString(string $record): static
    {
        $builder = new static;

        $properties = [];

        // Parse the DMARC record
        foreach (explode(';', $record) as $part) {
            $property = explode('=', trim($part));

            if (count($property) !== 2) {
                continue;
            }

            $key = trim($property[0]);
            $value = trim($property[1]);

            // Clean common issues:
            // 1. Remove trailing periods (common in DNS zone files)
            // 2. Remove surrounding quotes
            // 3. Trim whitespace
            $value = rtrim($value, '.');
            $value = trim($value, '"\'');
            $value = trim($value);

            $properties[$key] = $value;
        }

        // Validate required fields
        if (!isset($properties['v'])) {
            throw InvalidDmarcRecordException::missingKey('v', 'DMARC version is required');
        }

        if (!isset($properties['p'])) {
            throw InvalidDmarcRecordException::missingKey('p', 'DMARC policy is required');
        }

        // Parse each property
        foreach ($properties as $key => $value) {
            match ($key) {
                'v' => $builder->version($value),
                'p' => $builder->policy($value),
                'sp' => $builder->subdomainPolicy($value),
                'rua' => $builder->rua($value),
                'ruf' => $builder->ruf($value),
                'adkim' => $builder->adkim($value),
                'aspf' => $builder->aspf($value),
                'fo' => $builder->reportingFromString($value),
                'pct' => $builder->percentage((int) $value),
                'ri' => $builder->reportInterval((int) $value),
                'np' => $builder->nonExistentSubdomainPolicy($value),
                'psd' => $builder->publicSuffixDomainPolicy($value),
                't' => $builder->testingMode($value),
                default => null,
            };
        }

        return $builder;
    }

    /**
     * Get the version
     */
    public function version(?string $version): static
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Get/set the policy
     */
    public function policy(DmarcPolicy|string|null $policy): static
    {
        $this->setPolicy($policy);
        return $this;
    }

    /**
     * Get/set the subdomain policy
     */
    public function subdomainPolicy(DmarcPolicy|string|null $policy): static
    {
        $this->setSubdomainPolicy($policy);
        return $this;
    }

    /**
     * Get/set RUA (aggregate report) URIs
     */
    public function rua(string|array|null $mailto): static
    {
        $this->rua = $this->normalizeReportUris($mailto);
        return $this;
    }

    /**
     * Get/set RUF (forensic report) URIs
     */
    public function ruf(string|array|null $mailto): static
    {
        $this->ruf = $this->normalizeReportUris($mailto);
        return $this;
    }

    /**
     * Normalize report URIs
     */
    protected function normalizeReportUris(string|array|null $value): ?string
    {
        if (is_null($value)) {
            return null;
        }
        
        $items = is_array($value) ? $value : explode(',', $value);
        
        $items = array_values(array_filter(
            array_map('trim', $items),
            fn (string $item): bool => $item !== ''
        ));
        
        foreach ($items as $item) {
            if (!str_starts_with($item, 'mailto:')) {
                throw InvalidDmarcRecordException::invalidFormat(
                    'mailto address should start with "mailto:"'
                );
            }
        }
        
        return $items === [] ? null : implode(',', $items);
    }

    /**
     * Get/set ADKIM (DKIM alignment)
     */
    public function adkim(DmarcAlignment|string|null $value): static
    {
        $this->setAdkim($value);
        return $this;
    }

    /**
     * Get/set ASPF (SPF alignment)
     */
    public function aspf(DmarcAlignment|string|null $value): static
    {
        $this->setAspf($value);
        return $this;
    }

    /**
     * Get/set reporting options (fo)
     */
    public function reporting(array $values = []): static
    {
        $values = array_values(array_unique($values));

        $processedValues = [];

        foreach ($values as $value) {
            if ($value instanceof DmarcReporting) {
                $processedValues[] = $value;
                continue;
            }

            // Convert to string and trim
            $value = trim((string)$value);

            // Check if it's a short code (0, 1, d, s)
            if (in_array($value, ['0', '1', 'd', 's'], true)) {
                try {
                    $processedValues[] = DmarcReporting::fromShortCode($value);
                    continue;
                } catch (\InvalidArgumentException $e) {
                    // Fall through to try from() method
                }
            }

            // Try to create from the full value (all, any, dkim, spf)
            try {
                $processedValues[] = DmarcReporting::from($value);
            } catch (\ValueError $e) {
                throw InvalidDmarcRecordException::invalidValue(
                    "Invalid reporting option: {$value}. Must be 0, 1, d, s, all, any, dkim, or spf"
                );
            }
        }

        // Check for mutually exclusive options (0 and 1 cannot be combined)
        $hasAll = in_array(DmarcReporting::ALL, $processedValues, true);
        $hasAny = in_array(DmarcReporting::ANY, $processedValues, true);

        if ($hasAll && $hasAny) {
            throw InvalidDmarcRecordException::conflict(
                'Reporting options "all" (0) and "any" (1) are mutually exclusive.'
            );
        }

        $this->reporting = $processedValues;

        return $this;
    }

    /**
     * Parse reporting options from string
     */
    public function reportingFromString(string $value): static
    {
        // Remove whitespace and trim
        $value = trim($value);

        // Clean the value - remove trailing periods and quotes
        $value = rtrim($value, '.');
        $value = trim($value, '"\'');
        $value = trim($value);

        // Check if it's a colon-separated list (e.g., "0:1:d:s")
        if (str_contains($value, ':')) {
            $options = array_map(function($item) {
                $item = trim($item);
                $item = rtrim($item, '.');
                $item = trim($item, '"\'');
                return trim($item);
            }, explode(':', $value));
        } else {
            // Single value (e.g., "0", "1", "d", "s")
            $options = [trim($value)];
        }

        // Filter out empty values
        $options = array_filter($options, fn($v) => $v !== '');

        // Convert numeric values to their string representations
        $options = array_map(function($value) {
            if (is_numeric($value)) {
                return match((int)$value) {
                    0 => '0',
                    1 => '1',
                    default => (string)$value,
                };
            }
            return $value;
        }, $options);

        $this->reporting($options);
        return $this;
    }

    /**
     * Get/set percentage (pct)
     */
    public function percentage(?int $percentage): static
    {
        if (!is_null($percentage) && ($percentage < 0 || $percentage > 100)) {
            throw InvalidDmarcRecordException::invalidValue(
                'Percentage must be between 0 and 100'
            );
        }
        $this->percentage = $percentage;
        return $this;
    }

    /**
     * Get/set reporting interval (ri)
     */
    public function reportInterval(?int $interval): static
    {
        if (!is_null($interval) && $interval < 0) {
            throw InvalidDmarcRecordException::invalidValue(
                'Reporting interval must be a positive integer'
            );
        }
        $this->reportInterval = $interval;
        return $this;
    }

    /**
     * Get/set non-existent subdomain policy (np)
     */
    public function nonExistentSubdomainPolicy(DmarcPolicy|string|null $policy): static
    {
        $this->setNonExistentSubdomainPolicy($policy);
        return $this;
    }

    /**
     * Get/set public suffix domain policy (psd)
     */
    public function publicSuffixDomainPolicy(?string $policy): static
    {
        if (!is_null($policy) && !in_array($policy, ['y', 'n', 'u', null], true)) {
            throw InvalidDmarcRecordException::invalidValue(
                'PSD must be "y", "n", or "u"'
            );
        }
        $this->publicSuffixDomainPolicy = $policy;
        return $this;
    }

    /**
     * Get/set testing mode (t)
     */
    public function testingMode(DmarcTesting|string|null $testingMode): static
    {
        $this->setTestingMode($testingMode);
        return $this;
    }

    /**
     * Convert to a DNS record string
     */
    public function toDnsRecord(): string
    {
        $parts = [];
        
        if ($this->version) {
            $parts[] = "v={$this->version}";
        }
        
        if ($this->policy) {
            $parts[] = "p={$this->policy->value}";
        }
        
        if ($this->subdomainPolicy) {
            $parts[] = "sp={$this->subdomainPolicy->value}";
        }
        
        if ($this->rua) {
            $parts[] = "rua={$this->rua}";
        }
        
        if ($this->ruf) {
            $parts[] = "ruf={$this->ruf}";
        }
        
        if ($this->adkim) {
            $parts[] = "adkim={$this->adkim->getShortCode()}";
        }
        
        if ($this->aspf) {
            $parts[] = "aspf={$this->aspf->getShortCode()}";
        }
        
        if (!empty($this->reporting)) {
            $foParts = array_map(
                fn(DmarcReporting $option) => $option->getShortCode(),
                $this->reporting
            );
            $parts[] = "fo=" . implode(':', $foParts);
        }
        
        if (!is_null($this->percentage)) {
            $parts[] = "pct={$this->percentage}";
        }
        
        if (!is_null($this->reportInterval)) {
            $parts[] = "ri={$this->reportInterval}";
        }

        
        if ($this->nonExistentSubdomainPolicy) {
            $parts[] = "np={$this->nonExistentSubdomainPolicy->value}";
        }
        
        if ($this->publicSuffixDomainPolicy) {
            $parts[] = "psd={$this->publicSuffixDomainPolicy}";
        }
        
        if ($this->testingMode) {
            $parts[] = "t={$this->testingMode->value}";
        }
        
        return implode('; ', $parts);
    }

    /**
     * Get all validation rules for Filament forms
     */
    public static function getValidationRules(): array
    {
        return [
            'policy' => ['required', 'string', 'in:none,quarantine,reject'],
            'subdomain_policy' => ['nullable', 'string', 'in:none,quarantine,reject'],
            'rua' => ['nullable', 'array'],
            'rua.*' => ['string', 'starts_with:mailto:'],
            'ruf' => ['nullable', 'array'],
            'ruf.*' => ['string', 'starts_with:mailto:'],
            'adkim' => ['nullable', 'string', 'in:relaxed,strict'],
            'aspf' => ['nullable', 'string', 'in:relaxed,strict'],
            'reporting' => ['nullable', 'array'],
            'reporting.*' => ['string', 'in:all,any,dkim,spf'],
            'percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'report_interval' => ['nullable', 'integer', 'min:0'],
            'np' => ['nullable', 'string', 'in:none,quarantine,reject'],
            'psd' => ['nullable', 'string', 'in:y,n,u'],
            't' => ['nullable', 'string', 'in:y,n'],
        ];
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'policy' => $this->policy?->value,
            'subdomain_policy' => $this->subdomainPolicy?->value,
            'rua' => $this->rua ? explode(',', $this->rua) : null,
            'ruf' => $this->ruf ? explode(',', $this->ruf) : null,
            'adkim' => $this->adkim?->value,
            'aspf' => $this->aspf?->value,
            'reporting' => array_map(fn($opt) => $opt->value, $this->reporting),
            'percentage' => $this->percentage,
            'report_interval' => $this->reportInterval,
            'np' => $this->nonExistentSubdomainPolicy?->value,
            'psd' => $this->publicSuffixDomainPolicy,
            't' => $this->testingMode?->value,
            'dns_record' => $this->toDnsRecord(),
        ];
    }

    /**
     * Convert to JSON
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * JSON serialize
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Magic method for string conversion
     */
    public function __toString(): string
    {
        return $this->toDnsRecord();
    }

    // Private setter methods with validation
    
    private function setPolicy(DmarcPolicy|string|null $policy): void
    {
        if (is_null($policy)) {
            $this->policy = null;
            return;
        }
        
        $this->policy = $policy instanceof DmarcPolicy 
            ? $policy 
            : DmarcPolicy::from($policy);
    }

    private function setSubdomainPolicy(DmarcPolicy|string|null $policy): void
    {
        if (is_null($policy)) {
            $this->subdomainPolicy = null;
            return;
        }
        
        $this->subdomainPolicy = $policy instanceof DmarcPolicy 
            ? $policy 
            : DmarcPolicy::from($policy);
    }

    private function setAdkim(DmarcAlignment|string|null $value): void
    {
        if (is_null($value)) {
            $this->adkim = null;
            return;
        }

        if ($value instanceof DmarcAlignment) {
            $this->adkim = $value;
            return;
        }

        // Check if it's a short code (r or s)
        if (in_array($value, ['r', 's'], true)) {
            $this->adkim = DmarcAlignment::fromShortCode($value);
            return;
        }

        // Try to create from the string value directly
        try {
            $this->adkim = DmarcAlignment::from($value);
        } catch (\ValueError $e) {
            throw InvalidDmarcRecordException::invalidValue(
                "Invalid ADKIM value: '{$value}'. Must be 'relaxed', 'strict', 'r', or 's'"
            );
        }
    }

    private function setAspf(DmarcAlignment|string|null $value): void
    {
        if (is_null($value)) {
            $this->aspf = null;
            return;
        }

        if ($value instanceof DmarcAlignment) {
            $this->aspf = $value;
            return;
        }

        // Check if it's a short code (r or s)
        if (in_array($value, ['r', 's'], true)) {
            $this->aspf = DmarcAlignment::fromShortCode($value);
            return;
        }

        // Try to create from the string value directly
        try {
            $this->aspf = DmarcAlignment::from($value);
        } catch (\ValueError $e) {
            throw InvalidDmarcRecordException::invalidValue(
                "Invalid ASPF value: '{$value}'. Must be 'relaxed', 'strict', 'r', or 's'"
            );
        }
    }

    private function setNonExistentSubdomainPolicy(DmarcPolicy|string|null $policy): void
    {
        if (is_null($policy)) {
            $this->nonExistentSubdomainPolicy = null;
            return;
        }
        
        $this->nonExistentSubdomainPolicy = $policy instanceof DmarcPolicy 
            ? $policy 
            : DmarcPolicy::from($policy);
    }

    private function setTestingMode(DmarcTesting|string|null $testingMode): void
    {
        if (is_null($testingMode)) {
            $this->testingMode = null;
            return;
        }
        
        $this->testingMode = $testingMode instanceof DmarcTesting 
            ? $testingMode 
            : DmarcTesting::from($testingMode);
    }
    
    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getPolicy(): ?DmarcPolicy
    {
        return $this->policy;
    }

    public function getSubdomainPolicy(): ?DmarcPolicy
    {
        return $this->subdomainPolicy;
    }

    public function getRua(): ?string
    {
        return $this->rua;
    }

    public function getRuf(): ?string
    {
        return $this->ruf;
    }

    public function getAdkim(): ?DmarcAlignment
    {
        return $this->adkim;
    }

    public function getAspf(): ?DmarcAlignment
    {
        return $this->aspf;
    }

    public function getReporting(): array
    {
        return $this->reporting;
    }

    public function getPercentage(): ?int
    {
        return $this->percentage;
    }

    public function getReportInterval(): ?int
    {
        return $this->reportInterval;
    }

    public function getNonExistentSubdomainPolicy(): ?DmarcPolicy
    {
        return $this->nonExistentSubdomainPolicy;
    }

    public function getPublicSuffixDomainPolicy(): ?string
    {
        return $this->publicSuffixDomainPolicy;
    }

    public function getTestingMode(): ?DmarcTesting
    {
        return $this->testingMode;
    }    
}