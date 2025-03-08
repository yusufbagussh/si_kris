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
        Schema::table('bri.qris_notifications', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('id');

            $table->index('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bri.qris_transactions', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });
    }
};
