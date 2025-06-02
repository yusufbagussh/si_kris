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
        Schema::create('patient_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_payment_id')->constrained('patient_payments');
            $table->string('billing_no');
            $table->decimal('billing_amount', 20, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_payment_details');
    }
};
