<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

final class SeedDefaults
{
    /** Preserve existing rates, soft-deleted rows, and user edits on repeat runs. */
    public static function insertIfMissing(string $table, array $key, array $values): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (! DB::table($table)->where($key)->exists()) {
            DB::table($table)->insert($values);
        }
    }
}
