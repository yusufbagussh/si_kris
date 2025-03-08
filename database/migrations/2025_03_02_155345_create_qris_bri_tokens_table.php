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
        Schema::create('bri.qris_bri_tokens', function (Blueprint $table) {
            $table->id();
            $table->text('token');
            $table->string('token_type');
            $table->string('expires_in');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qris_bri_tokens');
    }
};
