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
        Schema::table('edc_payments', function (Blueprint $table) {
            $table->string('acq_mid')->nullable()->after('transaction_date')->comment('Merchant ID dari acquirer');
            $table->string('acq_tid')->nullable()->after('acq_mid')->comment('Terminal ID dari acquirer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edc_payments', function (Blueprint $table) {
            $table->dropColumn('acq_mid');
            $table->dropColumn('acq_tid');
        });
    }
};
