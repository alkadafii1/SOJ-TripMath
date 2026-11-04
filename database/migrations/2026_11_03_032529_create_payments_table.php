<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained();
            $table->string('payment_type');
            $table->unsignedInteger('installment_no')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('paid_at');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['trip_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};