<?php

declare(strict_types=1);

namespace VEximweb\Plugin\DnsTools\Dmarc\Enums;

enum DmarcAlignment: string
{
    case RELAXED = 'relaxed';
    case STRICT = 'strict';

    public function getShortCode(): string
    {
        return match($this) {
            self::RELAXED => 'r',
            self::STRICT => 's',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::RELAXED => 'Relaxed alignment (subdomains allowed)',
            self::STRICT => 'Strict alignment (exact match required)',
        };
    }

    public static function fromShortCode(string $code): self
    {
        return match($code) {
            'r' => self::RELAXED,
            's' => self::STRICT,
            default => throw new \InvalidArgumentException("Invalid alignment code: {$code}"),
        };
    }
}