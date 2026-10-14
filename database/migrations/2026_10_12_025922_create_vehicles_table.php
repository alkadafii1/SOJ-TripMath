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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('capacity');
            $table->decimal('standard_cost', 15, 2)->default(0);
            $table->string('cost_type')->default('per_day');
            $table->boolean('driver_included')->default(true);
            $table->boolean('fuel_included')->default(false);
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
