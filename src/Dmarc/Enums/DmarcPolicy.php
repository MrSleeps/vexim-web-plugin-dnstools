<?php

declare(strict_types=1);

namespace VEximweb\Plugin\DnsTools\Dmarc\Enums;

enum DmarcPolicy: string
{
    case NONE = 'none';
    case QUARANTINE = 'quarantine';
    case REJECT = 'reject';

    public function getDescription(): string
    {
        return match($this) {
            self::NONE => 'No action, monitor only',
            self::QUARANTINE => 'Mark as spam/quarantine',
            self::REJECT => 'Reject the email outright',
        };
    }

    public function getSeverity(): int
    {
        return match($this) {
            self::NONE => 0,
            self::QUARANTINE => 1,
            self::REJECT => 2,
        };
    }
}