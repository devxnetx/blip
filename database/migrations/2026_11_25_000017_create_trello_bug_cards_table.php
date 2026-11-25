<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_bug_cards', function (Blueprint $table) {
            $table->id();
            $table->string('discord_thread_id', 32)->unique();
            $table->string('trello_card_id', 32);
            $table->json('tags');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trello_bug_cards');
    }
};
