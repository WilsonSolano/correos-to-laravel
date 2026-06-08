<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_tokens', function (Blueprint $table) {
            $table->id();

            // Provider identifier, e.g. 'gmail', 'outlook'
            $table->string('provider')->unique();

            // Encrypted tokens
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->string('token_type')->default('Bearer');

            // Expiry metadata
            $table->unsignedInteger('expires_in')->nullable()->comment('Seconds from issue');
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_tokens');
    }
};
