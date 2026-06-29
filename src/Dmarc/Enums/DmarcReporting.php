<?php

declare(strict_types=1);

namespace VEximweb\Plugin\DnsTools\Dmarc\Enums;

enum DmarcReporting: string
{
    case ALL = 'all';
    case ANY = 'any';
    case DKIM = 'dkim';
    case SPF = 'spf';

    public function getShortCode(): string
    {
        return match($this) {
            self::ALL => '0',
            self::ANY => '1',
            self::DKIM => 'd',
            self::SPF => 's',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::ALL => 'Report all failures',
            self::ANY => 'Report if either DKIM or SPF fails',
            self::DKIM => 'Report only DKIM failures',
            self::SPF => 'Report only SPF failures',
        };
    }

    public static function fromShortCode(string $code): self
    {
        return match($code) {
            '0' => self::ALL,
            '1' => self::ANY,
            'd' => self::DKIM,
            's' => self::SPF,
            default => throw new \InvalidArgumentException("Invalid reporting option code: {$code}"),
        };
    }
}