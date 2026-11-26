<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_snapshots', function (Blueprint $table) {
            // Nullable for legacy snapshots: do not invent historical values during migration.
            $table->decimal('recommended_price', 15, 2)->nullable();
            $table->unsignedInteger('effective_pax')->nullable();
            $table->date('trip_end_date')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('trip_snapshots', function (Blueprint $table) {
            $table->dropIndex(['trip_end_date']);
            $table->dropColumn(['recommended_price', 'effective_pax', 'trip_end_date']);
        });
    }
};
