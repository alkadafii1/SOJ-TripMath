<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_rules', function (Blueprint $table) {
            $table->id();
            // Satu "paket" aturan (marketing + bagi hasil) berbagi rule_set yang sama
            $table->string('rule_set', 36)->index();
            $table->string('rule_type');          // marketing | profit_sharing
            $table->string('party');              // marketing | owner | partner
            $table->decimal('percentage', 7, 4);
            $table->string('calculation_base');   // actual_profit | revenue | distributable_profit
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_rules');
    }
};
