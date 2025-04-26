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
        Schema::table('qris_payments', function (Blueprint $table) {
            $table->foreignId('patient_payment_id')->nullable()->constrained('patient_payments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qris_payments', function (Blueprint $table) {
            $table->dropForeign(['patient_payment_id']);
            $table->dropColumn('patient_payment_id');
        });
    }
};
