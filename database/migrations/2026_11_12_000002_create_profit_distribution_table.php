<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_distribution', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained();
            $table->foreignId('profit_rule_id')->nullable()->constrained('profit_rules')->nullOnDelete();
            $table->string('party');
            $table->decimal('percentage', 7, 4);
            $table->decimal('amount', 15, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['trip_id', 'party']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_distribution');
    }
};
