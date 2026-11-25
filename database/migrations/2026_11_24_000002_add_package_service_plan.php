<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_versions', function (Blueprint $table) {
            // Nullable defaults keep existing templates usable without inventing a duration.
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->unsignedSmallInteger('nights')->nullable();
            $table->json('itinerary_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('package_versions', function (Blueprint $table) {
            $table->dropColumn(['duration_days', 'nights', 'itinerary_days']);
        });
    }
};
