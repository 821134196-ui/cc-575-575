<?php

namespace App\Services;

class Location
{
    public const WAREHOUSE = 'warehouse';
    public const AUDIENCE = 'audience';
    public const SCRAPPED = 'scrapped';

    public static function entrance(int $id): string
    {
        return "entrance:{$id}";
    }

    public static function label(string $loc): string
    {
        return match (true) {
            $loc === self::WAREHOUSE => '前台总仓',
            $loc === self::AUDIENCE => '已发放观众',
            $loc === self::SCRAPPED => '报废',
            str_starts_with($loc, 'entrance:') => '入口 '.substr($loc, 9),
            default => $loc,
        };
    }
}
