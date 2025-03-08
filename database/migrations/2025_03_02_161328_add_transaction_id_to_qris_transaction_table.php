<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bri.qris_transactions', function (Blueprint $table) {
            // Add the foreign key to hospital_transactions
            $table->foreignId('transaction_id')->nullable()->after('id')->constrained('bri.transactions');

            // Add expiry time field for QR
            $table->timestamp('expires_at')->nullable()->after('last_inquiry_at'); //2menit

            // Add index
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qris_transactions', function (Blueprint $table) {
            $table->dropForeign(['hospital_transaction_id']);
            $table->dropColumn('hospital_transaction_id');
            $table->dropColumn('expires_at');
        });
    }
};

