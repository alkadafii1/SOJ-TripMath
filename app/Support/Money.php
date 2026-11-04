<?php

namespace App\Support;

class Money
{
    public static function rp($value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    public static function percent($value): string
    {
    return number_format((float) $value, 2, ',', '.').'%';
    }

    /** Nominal bertanda untuk selisih, misalnya "+Rp 60.000" atau "-Rp 790.000". Hanya untuk tampilan. */
    public static function signed($value): string
    {
        $number = (float) $value;
        $sign = $number > 0 ? '+' : ($number < 0 ? '-' : '');

        return $sign.'Rp '.number_format(abs($number), 0, ',', '.');
    }
}