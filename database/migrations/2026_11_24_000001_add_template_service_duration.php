<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_version_items', function (Blueprint $table) {
            // Null preserves legacy templates that follow the whole trip duration.
            $table->unsignedInteger('duration_qty')->nullable();
        });
        Schema::table('trip_cost_items', function (Blueprint $table) {
            $table->unsignedInteger('day_no')->nullable();
            $table->boolean('follows_trip_duration')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('trip_cost_items', function (Blueprint $table) {
            $table->dropColumn(['day_no', 'follows_trip_duration']);
        });
        Schema::table('package_version_items', function (Blueprint $table) {
            $table->dropColumn('duration_qty');
        });
    }
};
