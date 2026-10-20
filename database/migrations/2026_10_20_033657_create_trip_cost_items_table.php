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
        Schema::create('trip_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('item_type');
            $table->unsignedBigInteger('item_id');
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->string('item_name');
            $table->string('cost_type');
            $table->string('cost_behavior')->default('variable');
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('duration_qty', 12, 4)->nullable();
            $table->unsignedInteger('unit_capacity')->nullable();
            $table->decimal('calculated_quantity', 12, 4)->nullable();
            $table->decimal('override_quantity', 12, 4)->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();
            $table->decimal('percentage', 7, 4)->nullable();
            $table->string('base_type')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_additional')->default(false);
            $table->decimal('standard_cost', 15, 2)->default(0);
            $table->decimal('negotiated_cost', 15, 2)->nullable();
            $table->decimal('actual_cost', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index('trip_id');
            $table->index(['item_type', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_cost_items');
    }
};
