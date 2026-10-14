<?php

namespace App\Support;

class Options
{
    public const STATUS = ['active' => 'Aktif', 'inactive' => 'Nonaktif'];

    public const COST_TYPES = [
        'per_person' => 'Per Person',
        'per_room' => 'Per Room',
        'per_vehicle' => 'Per Vehicle',
        'per_trip' => 'Per Trip',
        'per_day' => 'Per Day',
        'per_night' => 'Per Night',
        'percentage' => 'Percentage',
    ];

    public const COST_BEHAVIOR = [
        'fixed' => 'Fixed (tetap)',
        'variable' => 'Variable (ikut pax/durasi)',
    ];

    public const VENDOR_CATEGORIES = [
        'hotel' => 'Hotel', 'transport' => 'Transportasi', 'restoran' => 'Restoran',
        'aktivitas' => 'Aktivitas', 'destinasi' => 'Destinasi/Tiket', 'lainnya' => 'Lainnya',
    ];

    public const MEAL_TYPES = [
        'breakfast' => 'Sarapan', 'lunch' => 'Makan siang',
        'dinner' => 'Makan malam', 'snack' => 'Snack',
    ];

    public const COST_CATEGORIES = [
        'parkir' => 'Parkir', 'tol' => 'Tol', 'ferry' => 'Ferry',
        'air_mineral' => 'Air mineral', 'dokumentasi' => 'Dokumentasi',
        'operasional' => 'Operasional', 'lainnya' => 'Lainnya',
    ];

    /** Untuk aturan validasi: 'in:'.Options::keys(Options::STATUS) */
    public static function keys(array $options): string
    {
        return implode(',', array_keys($options));
    }
}