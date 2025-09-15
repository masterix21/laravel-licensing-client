<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licensing_client_cache', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('token')->nullable();
            $table->string('fingerprint')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('key');
            $table->index('fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licensing_client_cache');
    }
};
