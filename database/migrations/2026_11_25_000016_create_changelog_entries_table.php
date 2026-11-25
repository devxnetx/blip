<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->string('discord_channel_id', 32)->nullable();
            $table->string('discord_message_id', 32)->nullable();
            $table->boolean('discord_is_thread')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_entries');
    }
};
