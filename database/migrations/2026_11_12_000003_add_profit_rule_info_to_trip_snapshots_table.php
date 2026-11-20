<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_snapshots', function (Blueprint $table) {
            $table->string('marketing_base')->nullable()->after('marketing_percent');
            $table->string('profit_rule_set', 36)->nullable()->after('partner_share');
        });
    }

    public function down(): void
    {
        Schema::table('trip_snapshots', function (Blueprint $table) {
            $table->dropColumn(['marketing_base', 'profit_rule_set']);
        });
    }
};
