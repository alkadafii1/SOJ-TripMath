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
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('room_type');
            $table->decimal('standard_rate', 15, 2)->default(0);
            $table->unsignedInteger('occupancy')->default(2);
            $table->unsignedInteger('max_pax')->nullable();
            $table->decimal('extra_bed_cost', 15, 2)->nullable();
            $table->boolean('breakfast_included')->default(false);
            $table->string('cost_type')->default('per_room');
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
        Schema::dropIfExists('hotels');
    }
};
