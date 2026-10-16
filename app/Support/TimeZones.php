<?php

namespace App\Support;

/**
 * Some browsers still report time zones by their old names (Chrome says
 * "Asia/Calcutta"). Both names work in PHP, but the business is saved with
 * the current one so it reads properly everywhere.
 */
final class TimeZones
{
    /** Old name => current name, for the renamed zones people actually live in. */
    private const RENAMED = [
        'Asia/Calcutta' => 'Asia/Kolkata',
        'Asia/Katmandu' => 'Asia/Kathmandu',
        'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
        'Asia/Rangoon' => 'Asia/Yangon',
        'Asia/Ulan_Bator' => 'Asia/Ulaanbaatar',
        'Europe/Kiev' => 'Europe/Kyiv',
        'America/Godthab' => 'America/Nuuk',
        'Atlantic/Faeroe' => 'Atlantic/Faroe',
        'Pacific/Truk' => 'Pacific/Chuuk',
        'Pacific/Ponape' => 'Pacific/Pohnpei',
        'Pacific/Enderbury' => 'Pacific/Kanton',
    ];

    public static function current(string $zone): string
    {
        return self::RENAMED[$zone] ?? $zone;
    }
}
