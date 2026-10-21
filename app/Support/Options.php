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

    public const TRIP_TYPES = [
        'open_trip' => 'Open Trip',
        'private' => 'Private Trip',
        'one_day' => 'One Day Trip',
        'group_tour' => 'Group Tour',
        'honeymoon' => 'Honeymoon Trip',
        'jeep' => 'SOJ Experience / Jeep Trip',
        'custom' => 'Custom Trip',
    ];

    public const ITEM_TYPES = [
        'hotel' => 'Hotel',
        'vehicle' => 'Kendaraan',
        'activity' => 'Aktivitas',
        'meal' => 'Meal',
        'destination' => 'Destinasi',
        'cost_item' => 'Biaya lain',
    ];

    public const VERSION_STATUS = [
        'draft' => 'Draft',
        'active' => 'Aktif',
        'archived' => 'Arsip',
    ];

    public const PRICING_METHODS = [
    'cost_plus_margin' => 'Cost Plus Margin',
    'cost_plus_markup' => 'Cost Plus Markup',
    ];

    public const ROUNDING_RULES = [
        'exact' => 'Tanpa pembulatan',
        'nearest_5000' => 'Terdekat Rp5.000',
        'nearest_10000' => 'Terdekat Rp10.000',
        'nearest_50000' => 'Terdekat Rp50.000',
        'nearest_100000' => 'Terdekat Rp100.000',
    ];

    public const PRICING_MODES = [
        'dynamic' => 'Dinamis (biaya + margin)',
        'package' => 'Harga paket',
        'per_pax' => 'Per pax (Open Trip)',
    ];

    public const APPROVAL_STATUS = [
        'none' => 'Belum diajukan',
        'pending' => 'Menunggu persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    /** Untuk aturan validasi: 'in:'.Options::keys(Options::STATUS) */
    public static function keys(array $options): string
    {
        return implode(',', array_keys($options));
    }
}