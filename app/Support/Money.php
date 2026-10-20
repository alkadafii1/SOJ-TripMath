<?php

namespace App\Support;

class Money
{
    public static function rp($value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}