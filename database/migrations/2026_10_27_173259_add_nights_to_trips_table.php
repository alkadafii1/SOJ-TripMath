<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedInteger('nights')->nullable()->after('duration_days');
        });

        // Trip lama: isi malam dengan hari - 1, sama seperti perilaku sebelumnya
        DB::table('trips')->update([
            'nights' => DB::raw('CASE WHEN duration_days > 1 THEN duration_days - 1 ELSE 0 END'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('nights');
        });
    }
};
