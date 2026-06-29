<?php

declare(strict_types=1);

namespace VEximweb\Plugin\DnsTools\Dmarc\Enums;

enum DmarcTesting: string
{
    case YES = 'y';
    case NO = 'n';

    public function getDescription(): string
    {
        return match($this) {
            self::YES => 'Testing mode (don\'t apply policy)',
            self::NO => 'Normal mode (apply policy)',
        };
    }
}