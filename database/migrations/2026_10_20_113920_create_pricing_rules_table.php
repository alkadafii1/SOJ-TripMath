<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    Schema::create('pricing_rules', function (Blueprint $table) {
        $table->id();
        $table->string('method');
        $table->decimal('rate_percent', 7, 4);
        $table->string('rounding_rule')->default('exact');
        $table->decimal('min_margin_threshold', 7, 4)->nullable();
        $table->boolean('block_below_min')->default(false);
        $table->boolean('require_approval')->default(false);
        $table->date('effective_from')->nullable();
        $table->date('effective_to')->nullable();
        $table->string('status')->default('active');
        $table->timestamps();
        $table->softDeletes();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
