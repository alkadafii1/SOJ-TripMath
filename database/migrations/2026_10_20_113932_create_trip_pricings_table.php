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
    Schema::create('trip_pricing', function (Blueprint $table) {
        $table->id();
        $table->foreignId('trip_id')->unique()->constrained()->cascadeOnDelete();
        $table->foreignId('pricing_rule_id')->nullable()->constrained('pricing_rules')->nullOnDelete();
        $table->string('pricing_mode');
        $table->string('pricing_method');
        $table->decimal('rate_percent', 7, 4);
        $table->string('rounding_rule')->default('exact');
        $table->decimal('min_margin_threshold', 7, 4)->nullable();
        $table->boolean('block_below_min')->default(false);
        $table->boolean('require_approval')->default(false);
        $table->decimal('price_per_pax', 15, 2)->nullable();
        $table->decimal('recommended_price', 15, 2);
        $table->decimal('quoted_price', 15, 2)->nullable();
        $table->decimal('customer_requested_price', 15, 2)->nullable();
        $table->decimal('final_price', 15, 2)->nullable();
        $table->decimal('customer_discount', 15, 2)->nullable();
        $table->text('below_min_reason')->nullable();
        $table->string('approval_status')->default('none');
        $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('approved_at')->nullable();
        $table->text('payment_terms')->nullable();
        $table->date('quote_valid_until')->nullable();
        $table->text('terms')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_pricings');
    }
};
