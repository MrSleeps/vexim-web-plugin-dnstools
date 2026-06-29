<?php

declare(strict_types=1);

namespace VEximweb\Plugin\DnsTools\Dmarc\Exceptions;

use Exception;

class InvalidDmarcRecordException extends Exception
{
    public static function invalidValue(string $message): self
    {
        return new self("Invalid DMARC value: {$message}");
    }

    public static function missingKey(string $key, string $message): self
    {
        return new self("Missing required key '{$key}': {$message}");
    }

    public static function invalidFormat(string $message): self
    {
        return new self("Invalid DMARC format: {$message}");
    }

    public static function conflict(string $message): self
    {
        return new self("DMARC configuration conflict: {$message}");
    }
}